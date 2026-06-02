#!/usr/bin/env python3
"""Query B8AIadmin local trace logs by trace id or traceparent."""

from __future__ import annotations

import argparse
import glob
import json
import re
from pathlib import Path
from typing import Any


SENSITIVE_PARTS = (
    "authorization",
    "cookie",
    "password",
    "passwd",
    "token",
    "access_token",
    "refresh_token",
    "secret",
)


def normalize_trace_id(value: str) -> str:
    match = re.search(r"\b([a-fA-F0-9]{32})\b", value.strip())
    if not match:
        raise SystemExit("未找到 32 位 trace_id，请传入 trace_id 或完整 traceparent。")
    return match.group(1).lower()


def redact(value: Any) -> Any:
    if isinstance(value, dict):
        result: dict[Any, Any] = {}
        for key, item in value.items():
            key_text = str(key).lower()
            if any(part in key_text for part in SENSITIVE_PARTS):
                result[key] = "******"
            else:
                result[key] = redact(item)
        return result
    if isinstance(value, list):
        return [redact(item) for item in value]
    return value


def latest_files(log_dir: Path, pattern: str, max_files: int) -> list[Path]:
    files = [Path(path) for path in glob.glob(str(log_dir / pattern))]
    files.sort(key=lambda path: path.stat().st_mtime if path.exists() else 0, reverse=True)
    return files[:max_files]


def parse_otel_line(line: str) -> dict[str, Any] | None:
    match = re.match(r"^\[(?P<logged_at>[^\]]+)\]\s+(?P<channel>[^:]+):\s+(?P<message>.*)$", line.strip())
    if not match:
        return None
    message = re.sub(r"\s+\[\]\s+\[\]\s*$", "", match.group("message").strip())
    try:
        payload = json.loads(message)
    except json.JSONDecodeError:
        return None
    if not isinstance(payload, dict):
        return None
    payload["_logged_at"] = match.group("logged_at")
    payload["_channel"] = match.group("channel")
    return payload


def read_recent_lines(path: Path, max_lines: int) -> list[tuple[int, str]]:
    try:
        lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
    except OSError:
        return []
    start = max(0, len(lines) - max_lines)
    return [(index + 1, line) for index, line in enumerate(lines[start:], start)]


def collect_otel(log_dir: Path, pattern: str, trace_id: str, max_files: int, max_lines: int) -> list[dict[str, Any]]:
    entries: list[dict[str, Any]] = []
    for path in latest_files(log_dir, pattern, max_files):
        for line_no, line in read_recent_lines(path, max_lines):
            payload = parse_otel_line(line)
            if not payload:
                continue
            if str(payload.get("trace_id", "")).lower() != trace_id:
                continue
            payload["_file"] = str(path)
            payload["_line_no"] = line_no
            entries.append(payload)
    return entries


def collect_webman(log_dir: Path, trace_id: str, max_files: int, max_lines: int, context: int) -> list[dict[str, Any]]:
    matches: list[dict[str, Any]] = []
    for path in latest_files(log_dir, "webman-*.log", max_files):
        lines = read_recent_lines(path, max_lines)
        for pos, (line_no, line) in enumerate(lines):
            if trace_id not in line.lower():
                continue
            start = max(0, pos - context)
            end = min(len(lines), pos + context + 1)
            matches.append({
                "file": str(path),
                "line_no": line_no,
                "context": lines[start:end],
            })
    return matches


def print_request(entries: list[dict[str, Any]], show_payload: bool) -> None:
    print(f"\n== HTTP 请求日志: {len(entries)} 条 ==")
    for entry in entries:
        print(
            f"- {entry.get('time') or entry.get('_logged_at')} "
            f"{entry.get('method')} {entry.get('path')} "
            f"status={entry.get('status_code')} code={entry.get('business_code')} "
            f"duration_ms={entry.get('duration_ms')} "
            f"({entry.get('_file')}:{entry.get('_line_no')})"
        )
        exception = entry.get("exception")
        if exception:
            print("  exception:", json.dumps(redact(exception), ensure_ascii=False))
        if show_payload:
            payload = redact({key: value for key, value in entry.items() if not key.startswith("_")})
            print(json.dumps(payload, ensure_ascii=False, indent=2))


def print_sql(entries: list[dict[str, Any]], show_payload: bool) -> None:
    print(f"\n== SQL 日志: {len(entries)} 条 ==")
    for entry in entries:
        sql = str(entry.get("sql", "")).replace("\n", " ")
        if len(sql) > 240 and not show_payload:
            sql = sql[:240] + "...[truncated]"
        print(
            f"- {entry.get('time') or entry.get('_logged_at')} "
            f"runtime_seconds={entry.get('runtime_seconds')} "
            f"({entry.get('_file')}:{entry.get('_line_no')})"
        )
        print(f"  {sql}")


def print_webman(matches: list[dict[str, Any]]) -> None:
    print(f"\n== Webman 异常上下文: {len(matches)} 处 ==")
    for match in matches:
        print(f"- {match['file']}:{match['line_no']}")
        for line_no, line in match["context"]:
            prefix = ">" if line_no == match["line_no"] else " "
            print(f"{prefix} {line_no}: {line}")


def main() -> None:
    parser = argparse.ArgumentParser(description="按 trace_id 查询 B8AIadmin 本地 trace 日志。")
    parser.add_argument("trace", help="32 位 trace_id 或完整 traceparent")
    parser.add_argument("--server-dir", default="server", help="server 目录路径，默认 server")
    parser.add_argument("--max-files", type=int, default=10, help="每类日志最多读取的最新文件数")
    parser.add_argument("--max-lines", type=int, default=5000, help="每个日志文件最多读取的尾部行数")
    parser.add_argument("--context", type=int, default=80, help="Webman 异常日志命中行前后上下文行数")
    parser.add_argument("--show-payload", action="store_true", help="显示完整 HTTP payload，仍会脱敏敏感字段")
    args = parser.parse_args()

    trace_id = normalize_trace_id(args.trace)
    server_dir = Path(args.server_dir)
    log_dir = server_dir / "runtime" / "logs"
    if not log_dir.is_dir():
        raise SystemExit(f"日志目录不存在: {log_dir}")

    print(f"Trace ID: {trace_id}")
    print(f"Log dir: {log_dir}")

    request_entries = collect_otel(log_dir, "otel-request-*.log", trace_id, args.max_files, args.max_lines)
    sql_entries = collect_otel(log_dir, "otel-sql-*.log", trace_id, args.max_files, args.max_lines)
    webman_matches = collect_webman(log_dir, trace_id, args.max_files, args.max_lines, args.context)

    print_request(request_entries, args.show_payload)
    print_sql(sql_entries, args.show_payload)
    print_webman(webman_matches)

    if not request_entries and not sql_entries and not webman_matches:
        raise SystemExit("未在当前日志范围内找到该 trace，请调大 --max-files/--max-lines 或确认日志目录。")


if __name__ == "__main__":
    main()
