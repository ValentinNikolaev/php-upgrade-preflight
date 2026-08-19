#!/usr/bin/env python3
"""Dependency-free validation for the static GitHub Pages artifact."""

from __future__ import annotations

import argparse
from collections import Counter
from html.parser import HTMLParser
import json
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
        self.elements: list[tuple[str, dict[str, str | None]]] = []
        self.text_parts: list[str] = []

    def handle_decl(self, declaration: str) -> None:
        if declaration.strip().lower() == "doctype html":
            self.counts["doctype"] += 1

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = dict(attrs)
        self.counts[tag] += 1
        self.elements.append((tag, attributes))
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

    def handle_data(self, data: str) -> None:
        self.text_parts.append(data)

    @property
    def text(self) -> str:
        return " ".join(" ".join(self.text_parts).split())


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


def repository_root(explicit_root: Path | None) -> Path:
    candidates = (
        [explicit_root]
        if explicit_root is not None
        else [Path.cwd(), *Path(__file__).resolve().parents]
    )
    for candidate in candidates:
        if candidate is None:
            continue
        resolved = candidate.resolve()
        if (
            (resolved / "packages/core/src/Model/ReportMetadata.php").is_file()
            and (resolved / "composer.json").is_file()
        ):
            return resolved
    raise ValueError(
        "Could not locate the repository root containing ReportMetadata.php and composer.json."
    )


def repository_facts(repo_root: Path) -> dict[str, str]:
    metadata_path = repo_root / "packages/core/src/Model/ReportMetadata.php"
    metadata = metadata_path.read_text(encoding="utf-8")
    facts: dict[str, str] = {}
    for constant, key in (("TOOL_VERSION", "tool_version"), ("SCHEMA_VERSION", "schema_version")):
        match = re.search(
            rf"public\s+const\s+{constant}\s*=\s*['\"]([^'\"]+)['\"]\s*;",
            metadata,
        )
        if match is None:
            raise ValueError(f"Could not derive {constant} from {metadata_path}.")
        facts[key] = match.group(1)

    composer_path = repo_root / "composer.json"
    composer = json.loads(composer_path.read_text(encoding="utf-8"))
    license_name = composer.get("license")
    if not isinstance(license_name, str) or not license_name.strip():
        raise ValueError(f"Could not derive the project license from {composer_path}.")
    facts["license"] = license_name

    for package in ("core", "cli", "laravel"):
        package_manifest = repo_root / "packages" / package / "composer.json"
        package_data = json.loads(package_manifest.read_text(encoding="utf-8"))
        if package_data.get("license") != license_name:
            raise ValueError(
                f"{package_manifest} license does not match the root project license."
            )

    license_path = repo_root / "LICENSE"
    license_text = license_path.read_text(encoding="utf-8")
    if license_name == "MIT" and not license_text.startswith("MIT License"):
        raise ValueError("composer.json declares MIT, but LICENSE is not the MIT License text.")

    readme = (repo_root / "README.md").read_text(encoding="utf-8")
    legacy = re.search(
        r"Releases up to and including (v\d+\.\d+\.\d+).*?"
        r"PolyForm Noncommercial(?: License)? 1\.0\.0",
        readme,
        flags=re.DOTALL,
    )
    if legacy is None:
        raise ValueError("Could not derive the historical-license cutoff from README.md.")
    facts["legacy_cutoff"] = legacy.group(1)

    version_parts = facts["tool_version"].split(".")
    if len(version_parts) < 2 or not all(part.isdigit() for part in version_parts[:2]):
        raise ValueError("TOOL_VERSION must start with a numeric MAJOR.MINOR release line.")
    facts["release_constraint"] = f"^{version_parts[0]}.{version_parts[1]}"
    return facts


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


def parse_page(path: Path) -> tuple[str, SiteParser]:
    source = path.read_text(encoding="utf-8")
    parser = SiteParser()
    parser.feed(source)
    parser.close()
    return source, parser


def validate_page_structure(
    page_name: str, site_root: Path, parser: SiteParser
) -> list[str]:
    errors = list(parser.errors)
    if parser.stack:
        errors.append(f"{page_name} has unclosed HTML elements: " + ", ".join(parser.stack))
    if parser.counts["doctype"] != 1:
        errors.append(f"{page_name} must have exactly one HTML5 doctype.")
    if parser.counts["html"] != 1 or parser.html_lang != "en":
        errors.append(f'{page_name} must have one <html lang="en"> root.')
    if parser.counts["title"] != 1 or parser.counts["h1"] != 1 or parser.counts["main"] != 1:
        errors.append(f"{page_name} must contain exactly one title, h1, and main landmark.")
    if not parser.has_charset or not parser.has_viewport:
        errors.append(f"{page_name} must declare charset and viewport metadata.")

    duplicate_ids = sorted(name for name, count in Counter(parser.ids).items() if count > 1)
    if duplicate_ids:
        errors.append(f"{page_name} has duplicate HTML ids: " + ", ".join(duplicate_ids))

    for tag, attribute, reference in parser.references:
        parsed = urlsplit(reference)
        if parsed.path in ("", Path(page_name).name) and parsed.fragment:
            if parsed.fragment not in parser.ids:
                errors.append(f"Missing local fragment target in {page_name}: #{parsed.fragment}")
            continue
        try:
            target = local_target(site_root, reference)
        except ValueError as error:
            errors.append(str(error))
            continue
        if target is not None and not target.is_file():
            errors.append(f"Missing local {tag} {attribute} target in {page_name}: {reference}")
    return errors


def validate_theme_contrast(source: str) -> list[str]:
    errors: list[str] = []
    theme_properties = {
        "--bg": "page background",
        "--text": "primary text",
        "--text-muted": "muted text",
        "--accent": "action accent",
        "--accent-soft": "soft action surface",
        "--primary-foreground": "primary action foreground",
        "--focus": "focus indicator",
    }
    colors = {name: css_hex_values(source, name) for name in theme_properties}
    for property_name, label in theme_properties.items():
        if len(colors[property_name]) != 2:
            errors.append(
                f"Light and dark themes must each define a six-digit {property_name} "
                f"color for the {label}."
            )

    if all(len(values) == 2 for values in colors.values()):
        for index, theme in enumerate(("light", "dark")):
            checks = (
                ("primary text", colors["--text"][index], colors["--bg"][index], 4.5),
                ("muted text", colors["--text-muted"][index], colors["--bg"][index], 4.5),
                ("action link", colors["--accent"][index], colors["--bg"][index], 4.5),
                (
                    "primary CTA text",
                    colors["--primary-foreground"][index],
                    colors["--accent"][index],
                    4.5,
                ),
                ("focus indicator", colors["--focus"][index], colors["--bg"][index], 3.0),
                (
                    "accent on its soft surface",
                    colors["--accent"][index],
                    colors["--accent-soft"][index],
                    4.5,
                ),
            )
            for label, foreground, background, minimum in checks:
                ratio = contrast_ratio(foreground, background)
                if ratio < minimum:
                    errors.append(
                        f"The {theme}-theme {label} contrast is {ratio:.2f}:1; "
                        f"at least {minimum:.1f}:1 is required."
                    )

            red, green, blue = (
                int(colors["--accent"][index][offset:offset + 2], 16)
                for offset in (1, 3, 5)
            )
            if red <= green or red <= blue:
                errors.append(
                    f"The {theme}-theme action accent must remain in the warm, "
                    "Laravel-inspired red/coral family."
                )
    return errors


def validate(site_root: Path, repo_root: Path) -> list[str]:
    index = site_root / "index.html"
    if not index.is_file():
        return [f"Missing entry page: {index}"]

    source, parser = parse_page(index)
    errors = validate_page_structure("index.html", site_root, parser)

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

    errors.extend(validate_theme_contrast(source))
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

    touch_target_selectors = (
        ".install button",
        ".recording-toggle",
        ".site-nav a",
        ".docs-links a",
    )
    for selector in touch_target_selectors:
        rule = re.search(
            rf"{re.escape(selector)}[^{{}}]*\{{[^}}]*\bmin-height\s*:\s*44px\s*;",
            source,
        )
        if rule is None:
            errors.append(f"{selector} must expose a minimum 44px touch target.")

    facts = repository_facts(repo_root)
    required_claims = (
        f"v{facts['tool_version']}",
        f"schema {facts['schema_version']}",
        facts["license"],
        facts["legacy_cutoff"],
        "PolyForm Noncommercial 1.0.0",
        "mkdir php-upgrade-tools",
        f"php-upgrade-preflight/cli:{facts['release_constraint']}",
        f"php-upgrade-preflight/laravel:{facts['release_constraint']}",
        "composer require --dev",
        "not part of the analyzer's read-only execution guarantee",
    )
    for claim in required_claims:
        if claim not in parser.text:
            errors.append(f"Missing required licensing/install disclosure: {claim}")

    required_sections = ("quick-start", "features", "how-it-works", "demos", "scope")
    missing_sections = [section for section in required_sections if section not in parser.ids]
    if missing_sections:
        errors.append("Missing required page sections: " + ", ".join(missing_sections))
    else:
        positions = [parser.ids.index(section) for section in required_sections]
        if positions != sorted(positions):
            errors.append(
                "Page sections must follow the user journey: quick-start, features, "
                "how-it-works, demos, scope."
            )

    elements = parser.elements
    skip_links = [
        attrs.get("href")
        for tag, attrs in elements
        if tag == "a" and "skip-link" in attrs.get("class", "").split()
    ]
    main_ids = [attrs.get("id") for tag, attrs in elements if tag == "main"]
    if skip_links != ["#main-content"] or main_ids != ["main-content"]:
        errors.append("The page must provide one skip link to <main id=\"main-content\">.")

    primary_navs = [
        attrs for tag, attrs in elements
        if tag == "nav" and attrs.get("aria-label", "").lower() == "primary navigation"
    ]
    hrefs = [attrs.get("href", "") for tag, attrs in elements if tag == "a"]
    if len(primary_navs) != 1:
        errors.append('The page must expose one <nav aria-label="Primary navigation">.')
    for section in required_sections[:-1]:
        if f"#{section}" not in hrefs:
            errors.append(f"Primary journeys must link to #{section}.")

    wiki_links = [href for href in hrefs if "/wiki/" in href]
    if not any("/wiki/Getting-Started" in href for href in wiki_links):
        errors.append("The page must link directly to the Wiki Getting Started guide.")
    if not any("/wiki/Troubleshooting-and-FAQ" in href for href in wiki_links):
        errors.append("The quick start must link to the Wiki troubleshooting guide.")

    quick_start_claims = (
        "vendor/bin/upgrade-intel analyze",
        "--format=json",
        "--output=",
        "resolution.status",
    )
    for claim in quick_start_claims:
        if claim not in parser.text:
            errors.append(f"The quick start is missing its first-run contract: {claim}")

    not_found = site_root / "404.html"
    if not not_found.is_file():
        errors.append(f"Missing recovery page: {not_found}")
    else:
        not_found_source, not_found_parser = parse_page(not_found)
        errors.extend(validate_page_structure("404.html", site_root, not_found_parser))
        recovery_hrefs = [
            attrs.get("href", "")
            for tag, attrs in not_found_parser.elements
            if tag == "a"
        ]
        required_recovery_links = (
            "https://valentinnikolaev.github.io/php-upgrade-preflight/",
            "https://github.com/ValentinNikolaev/php-upgrade-preflight/wiki/Getting-Started",
            "https://github.com/ValentinNikolaev/php-upgrade-preflight",
        )
        for link in required_recovery_links:
            if link not in recovery_hrefs:
                errors.append(f"404.html is missing a recovery link: {link}")
        if "prefers-reduced-motion: reduce" not in not_found_source:
            errors.append("404.html must explicitly respect prefers-reduced-motion.")

    return errors


def main() -> int:
    argument_parser = argparse.ArgumentParser(description=__doc__)
    argument_parser.add_argument("--site-root", type=Path, default=Path("site"))
    argument_parser.add_argument(
        "--repo-root",
        type=Path,
        help="Repository root; auto-detected from the verifier location when omitted.",
    )
    arguments = argument_parser.parse_args()
    try:
        repo_root = repository_root(arguments.repo_root)
        errors = validate(arguments.site_root.resolve(), repo_root)
    except (OSError, ValueError, json.JSONDecodeError) as error:
        errors = [str(error)]
    if errors:
        for error in errors:
            print(f"ERROR: {error}")
        return 1
    print("Pages artifact validation passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
