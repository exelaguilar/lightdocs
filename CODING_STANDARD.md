# Coding Standard

## File types and encoding

- PHP source files use `.php`.
- View/template files use `.php`.
- File names are lowercase with words separated by underscores.
- Git stores files with LF line endings.

## PHP

- Use the lowercase `<?php` opening tag.
- PHP 2.0+ files do not require a closing tag.
- Use tabs for PHP and JavaScript indentation.
- Use 1TBS braces: `if ($value) {` and `} else {`.
- Put spaces around operators: `$value = 1;`.
- Type casts have no space: `(int)$value`.
- Always declare method visibility.
- Classes and methods use camel case.
- Variables and helper functions use lowercase snake case.
- User-defined constants use uppercase snake case.
- Use lowercase `true`, `false`, and `null`.
- Do not leave trailing whitespace, including on blank lines.

## Templates, HTML, and CSS

- PHP templates use tabs for indentation, same as application PHP.
- JavaScript uses tabs for indentation.
- HTML class and ID names use hyphens, not underscores: `my-class`.

## MVC conventions

- Controllers live in `{context}/controller/{route}/{name}.php`.
- Models live in `{context}/model/{route}/{name}.php`.
- Templates live in `{context}/view/template/{route}/{name}.php`.
- Controller and model file names never include `_controller` or `_model`.
- Native actions use OpenCart/NeverNote route names such as `common/home` or `editor/editor.save`.
- A controller's registered route determines its file location — e.g. route `common/dashboard` lives at `controller/common/dashboard.php`, not in a folder named after the controller itself.
- Shared framework code belongs in `system/engine`, `system/helper`, or `system/library`.
