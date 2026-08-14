# History of Tmpl2

Tmpl2 is a lightweight PHP template engine whose origins date back
to the early PHP 4 era.

## 2001 — Initial development

The original template engine was developed for PHP 4.1-era web
applications.

The design was intentionally simple:

- HTML templates remain ordinary HTML files.
- Template variables use `%NAME%` notation.
- PHP code and HTML templates are separated.
- Loops and conditional blocks are expressed using HTML comments.

## 2001–2002 — tmpl2

The engine evolved into `tmpl2`, adding loop processing and
conditional template directives.

Historical source headers include revisions such as:

    tmpl2.class.inc,v 1.9 2001/12/24
    tmpl2.class.php,v 1.18 2002/10/20

The library continued to be used and maintained in PHP applications
for many years.

## 2023 — Conditional processing fixes

Several parts of the conditional processing implementation were
reviewed and corrected while maintaining compatibility with existing
templates.

## 2026 — Tmpl2 2.0

Development of Tmpl2 2.0 began as a modernization of the original
engine.

The goal was not to replace Tmpl2 with a modern framework, but to
preserve its small and straightforward template model while making
the implementation maintainable on modern PHP.

Major internal structures were redesigned using typed classes while
preserving the original template syntax.

Tmpl2 2.0 supports PHP 7.4 and later.
