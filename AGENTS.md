## Maintaining This File

Keep it short. Add a rule only when it isn't obvious from the code or the WordPress handbook, for example after an agent gets something wrong. Remove rules that no longer apply. Keep this section at the top of this file.

Project-specific rules go under **Project**. Add only what reading the code won't reveal: decisions, constraints, workflow steps, or files that are easy to edit by mistake. Don't describe structure or conventions the code already shows.

## Standards

Follow the WordPress Coding Standards and the WordPress security guidelines.

Validate and sanitize input, escape output, and verify nonces and user capabilities where required.

Preserve existing public APIs, hooks, filters, and backward compatibility unless explicitly asked to change them.

Keep changes focused on the requested task. Avoid unrelated refactoring.

## Code Quality

The goal is code that people can read easily, so it's quick to understand and cheap to change.

Balance the principles of *Clean Code* (Robert C. Martin) and *A Philosophy of Software Design* (John Ousterhout). Keep functions focused without excessive fragmentation or unnecessary abstraction.

- Names should show intent. Avoid unclear abbreviations.
- Keep functions cohesive and interfaces simple.
- Prefer deep modules: a simple interface that hides complex work behind it. Avoid shallow wrappers and pass-through methods.
- Return early instead of nesting conditions.
- Avoid unnecessary duplication and dead code in changed areas.
- Make methods and properties `private` by default. Use `public` only when another class or a WordPress hook callback needs access.
- Prefer self-explanatory code. Use comments to explain non-obvious intent, trade-offs, workarounds, or WordPress behavior rather than restating the code.
- Give every function a minimal DocBlock: a one-line summary plus `@param` and `@return` tags. Add more only when it tells the reader something the code can't.
- Favor consistency with existing code unless there's a clear reason to improve it.

## Project

Suppress a PHPCS rule only for the line that needs it, with `// phpcs:ignore Sniff.Name -- reason`. Put it at the end of the line, or on the line above when that would push the line past about 120 characters. Never use file-level `phpcs:disable`.
