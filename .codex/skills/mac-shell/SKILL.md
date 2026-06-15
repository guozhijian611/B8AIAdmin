---
name: mac-shell
description: macOS/zsh 命令行操作技能。Use when Codex needs to write, run, or repair Mac shell commands, especially commands that read .env files, pass secrets to CLI tools, avoid zsh/bash quoting conflicts, handle spaces in paths, use BSD sed/awk differences, or produce copy-pasteable command snippets for macOS.
---

# Mac Shell 命令行操作

## 核心原则

- 先判断当前 shell，macOS 默认按 zsh 处理；不要默认 GNU sed、GNU awk 或 Linux 专属参数可用。
- 读 `.env` 时不要 `source .env`、`eval $(cat .env)` 或把值拼进未加引号的命令。`.env` 里常见的 `$`、`!`、反引号、空格、单引号、双引号都可能触发 shell 二次解释。
- 对包含密码、Token、Cookie、Secret 的值，命令输出和最终回复只展示变量名或脱敏值，除非用户明确要求原文。
- 生成可复制命令时优先用多行脚本、变量和数组，避免把复杂值嵌在一层 shell 字符串里。
- 路径始终加双引号；命令参数用数组承载时再执行 `"${cmd[@]}"`，减少空格和特殊字符风险。
- 输出文本用 `printf '%s\n' "$value"`，不要用 `echo` 处理不可信内容。

## 读取 .env

首选使用本 skill 的脚本，它不会执行 `.env` 内容，也不会展开 `$VAR`：

```bash
python3 .codex/skills/mac-shell/scripts/read_env_value.py server/.env DB_PASSWORD
```

需要把取到的值安全地粘回命令行时，输出 shell-quoted 形式：

```bash
python3 .codex/skills/mac-shell/scripts/read_env_value.py --shell-quote server/.env DB_PASSWORD
```

在脚本里使用变量，不要把值内联到命令文本：

```bash
env_file="server/.env"
db_host="$(python3 .codex/skills/mac-shell/scripts/read_env_value.py "$env_file" DB_HOST)"
db_user="$(python3 .codex/skills/mac-shell/scripts/read_env_value.py "$env_file" DB_USER)"
db_pass="$(python3 .codex/skills/mac-shell/scripts/read_env_value.py "$env_file" DB_PASSWORD)"
db_name="$(python3 .codex/skills/mac-shell/scripts/read_env_value.py "$env_file" DB_NAME)"

cmd=(mysql -h "$db_host" -u "$db_user" "-p$db_pass" "$db_name")
"${cmd[@]}"
```

只想临时查看非敏感配置时：

```bash
python3 .codex/skills/mac-shell/scripts/read_env_value.py server/.env APP_DEBUG
```

## 无脚本时的安全退路

如果不能使用本 skill 脚本，使用 `awk -v key=...` 传键名，避免 shell 在正则中解释变量：

```bash
awk -v key="DB_NAME" '
  $0 ~ "^[[:space:]]*(export[[:space:]]+)?" key "[[:space:]]*=" {
    sub(/^[^=]*=/, "")
    sub(/^[[:space:]]*/, "")
    print
    exit
  }
' server/.env
```

这个退路只适合读取简单值；遇到单双引号、行尾注释、转义字符或密码时，改用脚本。

## macOS 常见命令差异

- `sed -i` 在 macOS 需要备份后缀：`sed -i '' 's/old/new/g' file`。
- `date`、`stat`、`readlink` 的参数和 GNU 版本不同；需要跨平台时先查当前命令帮助或用 Python/Node 替代。
- `grep -P` 通常不可用，优先用 `rg`、`perl -ne` 或标准 `awk`。
- `lsof -nP -iTCP:3306 -sTCP:LISTEN` 可查端口监听；配合 `launchctl print gui/$(id -u)` 查 LaunchAgent。
- 复制到剪贴板用 `pbcopy`，读取剪贴板用 `pbpaste`；不要把敏感值自动复制，除非用户明确要求。

## 生成命令的格式

给用户命令时优先给完整可复制块：

```bash
cd "/path/to/project"
env_file="server/.env"
value="$(python3 .codex/skills/mac-shell/scripts/read_env_value.py "$env_file" KEY_NAME)"
printf '%s\n' "$value"
```

需要在 `exec_command` 中运行复杂命令时，用多行字符串；不要用 `cmd1; cmd2 && cmd3` 串成难读的一行。涉及只读探测时可以直接执行；涉及删除、覆盖、生产迁移、服务重启或写入远端环境时先向用户确认。

## 排障判断

- 报 `parse error near`、`unmatched '`、`bad substitution`：优先怀疑命令被错误嵌套引号截断。
- 报 `event not found`：zsh 历史展开吃到了 `!`，不要把密码直接放在命令字面量里。
- 报 `no matches found`：zsh 把 `[]`、`*`、`?` 当 glob；给参数加引号或用数组。
- `.env` 取值结果多出引号：说明用了纯文本截取；换成本 skill 脚本解析。
- `.env` 取值里的 `$FOO` 被替换：说明经过了 `source`、`eval` 或双引号内二次展开；改成脚本读取并用变量传参。

## 提交前检查

修改此 skill 后运行：

```bash
uv run --with pyyaml python /Users/openb8/.codex/skills/.system/skill-creator/scripts/quick_validate.py .codex/skills/mac-shell
python3 .codex/skills/mac-shell/scripts/read_env_value.py --help
```
