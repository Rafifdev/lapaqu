# AI Agent Guidelines & Ponytail Skill Integration

## Overview
This repository is the backend for **pf-backend** (Laravel 12 / PHP 8.3).
All AI agents operating in this workspace follow the **Ponytail** philosophy: * The best code is the code you never wrote.*

---

## 🧗 Ponytail Principles & Decision Ladder

Before writing, refactoring, or generating code, evaluate against the **Decision Ladder**:

1. **Does this need to exist at all? (YAGNI)**
   - Speculative need = skip it, say so in one line.
2. **Already in this codebase?**
   - Reuse existing helpers, traits, models, actions, or utilities. Look before writing new code.
3. **Stdlib / Framework does it?**
   - Prefer native PHP functions and built-in Laravel features over custom implementations.
4. **Native platform feature covers it?**
   - Use database constraints, native HTTP/HTML capabilities, or OS features when available.
5. **Already-installed dependency solves it?**
   - Check composer.json and package.json before adding any new library.
6. **Can it be one line?**
   - Keep it concise.
7. **Only then:**
   - Write the absolute minimum code that works.

### Key Rules
- **No speculative abstractions**: No interfaces with only one implementation, no unnecessary design patterns.
- **Deletion over addition**: The cleanest code is simplified or deleted code.
- **Fix root causes**: Don't patch symptoms across multiple callers. Fix the shared single point.
- **Safety first**: Never cut corners on input validation, security, database transaction integrity, or error handling.

---

## 🛠 Available Ponytail Skills

Skills are installed in .agents/skills/ (and .claude/skills/, skills/):

- ponytail (Default mode: enforces minimal solutions & ladder)
- ponytail-review (Reviews diffs/PRs specifically for over-engineering and bloat)
- ponytail-audit (Scans repo for dead code, unneeded abstractions, and bloat)
- ponytail-debt (Scans and aggregates ponytail: comments into a debt ledger)
- ponytail-gain (Displays measured impact & benchmarks)
- ponytail-help (Quick-reference card for commands & intensity levels)

---

##  Intensity Modes

| Mode | Command | Behavior |
|---|---|---|
| **lite** | /ponytail lite | Build as asked, but propose the lazier alternative in 1 line. |
| **full** | /ponytail (default) | Ladder strictly enforced. Minimum diff, stdlib first. |
| **ultra** | /ponytail ultra | YAGNI extremist. Deletion first, challenge requirements. |

---

## 🐘 Laravel & Backend Conventions
- **PHP 8.3 & Laravel 12**: Use modern PHP syntax (typed properties, match expressions, constructor promotion, readonly).
- **Database & Migrations**: Ensure proper indexes, foreign keys, and database constraints.
- **Testing**: Maintain feature and unit tests (php artisan test) with PHPUnit/Pest.
