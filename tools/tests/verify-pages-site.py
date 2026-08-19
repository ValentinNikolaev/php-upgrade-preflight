#!/usr/bin/env python3
"""Dependency-free validation for the static GitHub Pages artifact."""

from __future__ import annotations

import argparse
from collections import Counter
from html.parser import HTMLParser
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit


VOID_ELEMENTS = {
    "area", "base", "br", "col", "embed", "hr", "img", "input", "link",
    "meta", "param", "source", "track", "wbr",
}


class SiteParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.errors: list[str] = []
        self.stack: list[str] = []
        self.ids: list[str] = []
        self.references: list[tuple[str, str, str]] = []
        self.counts: Counter[str] = Counter()
        self.html_lang: str | None = None
        self.has_viewport = False
        self.has_charset = False
        self.has_live_status = False
        self.copy_button_label: str | None = None
        self.recording_controls = 0

    def handle_decl(self, declaration: str) -> None:
        if declaration.strip().lower() == "doctype html":
            self.counts["doctype"] += 1

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = dict(attrs)
        self.counts[tag] += 1
        if tag not in VOID_ELEMENTS:
            self.stack.append(tag)

        element_id = attributes.get("id")
        if element_id:
            self.ids.append(element_id)

        if tag == "html":
            self.html_lang = attributes.get("lang")
        if tag == "meta" and "charset" in attributes:
            self.has_charset = True
        if tag == "meta" and attributes.get("name", "").lower() == "viewport":
            self.has_viewport = True
        if attributes.get("aria-live") == "polite" or attributes.get("role") == "status":
            self.has_live_status = True
        if tag == "button" and element_id == "copy-btn":
            self.copy_button_label = attributes.get("aria-label")
        if tag == "button" and "recording-toggle" in attributes.get("class", "").split():
            self.recording_controls += 1
            if attributes.get("aria-pressed") != "false":
                self.errors.append("Recording controls must start with aria-pressed=false.")

        if tag == "img" and "alt" not in attributes:
            self.errors.append("Every image must have an alt attribute.")
        if tag in {"audio", "video"} and "autoplay" in attributes:
            self.errors.append(f"<{tag}> must not autoplay.")
        if tag == "video" and "controls" not in attributes:
            self.errors.append("Every video must expose controls.")

        for attribute in ("href", "src", "poster", "data-recording"):
            value = attributes.get(attribute)
            if value:
                self.references.append((tag, attribute, value))
                if tag == "img" and attribute == "src" and urlsplit(value).path.lower().endswith(".gif"):
                    self.errors.append("Animated GIFs must not be embedded as auto-loading <img> elements.")

    def handle_startendtag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.handle_starttag(tag, attrs)
        if tag not in VOID_ELEMENTS and self.stack and self.stack[-1] == tag:
            self.stack.pop()

    def handle_endtag(self, tag: str) -> None:
        if not self.stack:
            self.errors.append(f"Unexpected closing </{tag}>.")
            return
        if self.stack[-1] != tag:
            self.errors.append(f"Closing </{tag}> does not match open <{self.stack[-1]}>.")
            if tag in self.stack:
                while self.stack and self.stack[-1] != tag:
                    self.stack.pop()
        if self.stack and self.stack[-1] == tag:
            self.stack.pop()


def local_target(site_root: Path, raw_reference: str) -> Path | None:
    parsed = urlsplit(raw_reference)
    if parsed.scheme or parsed.netloc or raw_reference.startswith(("#", "data:", "mailto:")):
        return None
    relative = unquote(parsed.path)
    if not relative:
        return None
    root = site_root.resolve()
    target = (root / relative.lstrip("/")).resolve()
    try:
        target.relative_to(root)
    except ValueError as error:
        raise ValueError(f"Local reference escapes the Pages artifact: {raw_reference}") from error
    return target


def css_hex_values(source: str, property_name: str) -> list[str]:
    pattern = rf"{re.escape(property_name)}\s*:\s*(#[0-9a-fA-F]{{6}})\s*;"
    return re.findall(pattern, source)


def relative_luminance(hex_color: str) -> float:
    channels = [int(hex_color[index:index + 2], 16) / 255 for index in (1, 3, 5)]
    linear = [
        value / 12.92 if value <= 0.04045 else ((value + 0.055) / 1.055) ** 2.4
        for value in channels
    ]
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]


def contrast_ratio(first: str, second: str) -> float:
    lighter, darker = sorted(
        (relative_luminance(first), relative_luminance(second)), reverse=True
    )
    return (lighter + 0.05) / (darker + 0.05)


def validate(site_root: Path) -> list[str]:
    index = site_root / "index.html"
    if not index.is_file():
        return [f"Missing entry page: {index}"]

    source = index.read_text(encoding="utf-8")
    parser = SiteParser()
    parser.feed(source)
    parser.close()
    errors = list(parser.errors)

    if parser.stack:
        errors.append("Unclosed HTML elements: " + ", ".join(parser.stack))
    if parser.counts["doctype"] != 1:
        errors.append("index.html must have exactly one HTML5 doctype.")
    if parser.counts["html"] != 1 or parser.html_lang != "en":
        errors.append('index.html must have one <html lang="en"> root.')
    if parser.counts["title"] != 1 or parser.counts["h1"] != 1 or parser.counts["main"] != 1:
        errors.append("index.html must contain exactly one title, h1, and main landmark.")
    if not parser.has_charset or not parser.has_viewport:
        errors.append("index.html must declare charset and viewport metadata.")

    duplicate_ids = sorted(name for name, count in Counter(parser.ids).items() if count > 1)
    if duplicate_ids:
        errors.append("Duplicate HTML ids: " + ", ".join(duplicate_ids))

    for tag, attribute, reference in parser.references:
        try:
            target = local_target(site_root, reference)
        except ValueError as error:
            errors.append(str(error))
            continue
        if target is not None and not target.is_file():
            errors.append(f"Missing local {tag} {attribute} target: {reference}")

    if parser.recording_controls != 4:
        errors.append("Expected four opt-in recording controls.")
    if not parser.copy_button_label:
        errors.append("The copy control must have an accessible label.")
    if not parser.has_live_status:
        errors.append("Interactive feedback must use an aria-live status.")
    if "prefers-reduced-motion: reduce" not in source:
        errors.append("The site must explicitly respect prefers-reduced-motion.")

    contrast_properties = {
        "--terminal-control-border": "recording control border",
        "--terminal-focus": "recording control focus indicator",
    }
    terminal_colors = css_hex_values(source, "--terminal")
    if not terminal_colors:
        errors.append("The terminal background must be an explicit six-digit hex color.")
    for property_name, label in contrast_properties.items():
        colors = css_hex_values(source, property_name)
        if len(colors) != 1:
            errors.append(f"{property_name} must have exactly one six-digit hex value.")
            continue
        for terminal_color in set(terminal_colors):
            ratio = contrast_ratio(colors[0], terminal_color)
            if ratio < 3.0:
                errors.append(
                    f"The {label} contrast against {terminal_color} is {ratio:.2f}:1; "
                    "at least 3:1 is required."
                )
    focus_colors = css_hex_values(source, "--terminal-focus")
    install_control_colors = css_hex_values(source, "--install-control-background")
    if len(install_control_colors) != 1:
        errors.append(
            "--install-control-background must have exactly one six-digit hex value."
        )
    elif len(focus_colors) == 1:
        ratio = contrast_ratio(focus_colors[0], install_control_colors[0])
        if ratio < 3.0:
            errors.append(
                f"The install control focus contrast is {ratio:.2f}:1; "
                "at least 3:1 is required."
            )

    accent_colors = css_hex_values(source, "--accent")
    primary_foregrounds = css_hex_values(source, "--primary-foreground")
    if len(accent_colors) != 2 or len(primary_foregrounds) != 2:
        errors.append(
            "Light and dark themes must each define six-digit --accent and "
            "--primary-foreground colors."
        )
    else:
        for theme, background, foreground in zip(
            ("light", "dark"), accent_colors, primary_foregrounds
        ):
            ratio = contrast_ratio(foreground, background)
            if ratio < 4.5:
                errors.append(
                    f"The {theme}-theme primary CTA text contrast is {ratio:.2f}:1; "
                    "at least 4.5:1 is required."
                )
    required_control_css = (
        "border: 1px solid var(--terminal-control-border)",
        ".recording-toggle:focus-visible { outline-color: var(--terminal-focus); }",
        "background: var(--install-control-background)",
        ".install button:focus-visible { outline-color: var(--terminal-focus); outline-offset: -4px; }",
        ".btn-primary { background: var(--accent); color: var(--primary-foreground);",
    )
    for marker in required_control_css:
        if marker not in source:
            errors.append(f"Missing accessible recording-control CSS: {marker}")

    required_claims = (
        "Repository HEAD · MIT",
        "v0.3.1 packages · PolyForm Noncommercial",
        "mkdir php-upgrade-tools",
        "composer require --dev php-upgrade-preflight/cli:^0.3",
        "not part of the analyzer's read-only execution guarantee",
    )
    for claim in required_claims:
        if claim not in source:
            errors.append(f"Missing required licensing/install disclosure: {claim}")

    return errors


def main() -> int:
    argument_parser = argparse.ArgumentParser(description=__doc__)
    argument_parser.add_argument("--site-root", type=Path, default=Path("site"))
    arguments = argument_parser.parse_args()
    errors = validate(arguments.site_root)
    if errors:
        for error in errors:
            print(f"ERROR: {error}")
        return 1
    print("Pages artifact validation passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
