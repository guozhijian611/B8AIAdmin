#!/usr/bin/env python3
"""Read one key from a .env file without evaluating it as shell code."""

from __future__ import annotations

import argparse
import re
import shlex
import sys
from pathlib import Path


KEY_RE = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")


def decode_double_quoted(value: str) -> str:
    result: list[str] = []
    i = 0
    while i < len(value):
        ch = value[i]
        if ch != "\\" or i + 1 >= len(value):
            result.append(ch)
            i += 1
            continue

        nxt = value[i + 1]
        escapes = {
            "n": "\n",
            "r": "\r",
            "t": "\t",
            "\\": "\\",
            '"': '"',
            "$": "$",
        }
        result.append(escapes.get(nxt, nxt))
        i += 2

    return "".join(result)


def strip_unquoted_comment(value: str) -> str:
    for index, ch in enumerate(value):
        if ch == "#" and (index == 0 or value[index - 1].isspace()):
            return value[:index].rstrip()
    return value.rstrip()


def parse_value(raw: str) -> str:
    value = raw.strip()
    if value == "":
        return ""

    quote = value[0]
    if quote == "'":
        end = value.find("'", 1)
        if end == -1:
            return value[1:]
        return value[1:end]

    if quote == '"':
        escaped = False
        chars: list[str] = []
        for ch in value[1:]:
            if escaped:
                chars.append("\\" + ch)
                escaped = False
                continue
            if ch == "\\":
                escaped = True
                continue
            if ch == '"':
                break
            chars.append(ch)
        if escaped:
            chars.append("\\")
        return decode_double_quoted("".join(chars))

    return strip_unquoted_comment(value)


def parse_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for line_number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue

        if stripped.startswith("export "):
            stripped = stripped[7:].lstrip()

        if "=" not in stripped:
            continue

        key, raw_value = stripped.split("=", 1)
        key = key.strip()
        if not KEY_RE.match(key):
            print(f"skip invalid key on line {line_number}: {key}", file=sys.stderr)
            continue

        values[key] = parse_value(raw_value)
    return values


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Read one .env key without sourcing or shell-evaluating the file."
    )
    parser.add_argument("env_file", help="Path to the .env file.")
    parser.add_argument("key", help="Environment key to read.")
    parser.add_argument(
        "--shell-quote",
        action="store_true",
        help="Print a safely shell-quoted value for copy-paste use.",
    )
    args = parser.parse_args()

    env_path = Path(args.env_file)
    if not env_path.is_file():
        print(f"env file not found: {env_path}", file=sys.stderr)
        return 2

    values = parse_env(env_path)
    if args.key not in values:
        print(f"key not found: {args.key}", file=sys.stderr)
        return 1

    value = values[args.key]
    if args.shell_quote:
        value = shlex.quote(value)

    sys.stdout.write(value)
    if not value.endswith("\n"):
        sys.stdout.write("\n")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
