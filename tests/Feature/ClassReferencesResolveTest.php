<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guards against a class reference that PHP cannot resolve — almost always a
 * missing `use` statement.
 *
 * This is a runtime-only fault. `php -l` reports the file as clean, static
 * analysis is not wired into this project, and the suite's 247 other tests all
 * passed while OrgAdminController::storeLogin() and ::destroy() were dead: an
 * unimported name resolves against the file's OWN namespace, so
 * `OrgAdminAccountProvisioner` became `App\Http\Controllers\OrgAdminAccountProvisioner`
 * and the container threw ReflectionException on every request. Creating a
 * cooperative's first administrator — and removing one — returned a 500.
 *
 * Nothing caught it because the sibling test resolves the provisioner directly,
 * and that test file has the import. Exercising a class is not the same as
 * exercising the route that reaches it.
 *
 * Signature-only checking would not have been enough either: `destroy()` broke on
 * `OrgAdminAccountEmail::` and `app(AdminContext::class)` in the method BODY,
 * where no type hint exists to reflect. So this walks tokens instead.
 */
class ClassReferencesResolveTest extends TestCase
{
    /** Names in class position that are not classes. */
    private const NOT_CLASSES = [
        'self', 'static', 'parent', 'int', 'string', 'bool', 'float', 'void',
        'array', 'object', 'mixed', 'callable', 'iterable', 'never', 'null',
        'false', 'true', 'class',
    ];

    public function test_every_class_reference_under_app_resolves(): void
    {
        $unresolved = [];

        foreach ($this->phpFilesUnderApp() as $file) {
            $tokens = token_get_all(file_get_contents($file));
            [$namespace, $imports] = $this->readNamespaceAndImports($tokens);

            foreach ($this->classPositionNames($tokens) as [$name, $line]) {
                $fqcn = $this->resolve($name, $namespace, $imports);

                if (class_exists($fqcn) || interface_exists($fqcn)
                    || trait_exists($fqcn) || enum_exists($fqcn)) {
                    continue;
                }

                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                $unresolved[] = "{$relative}:{$line} — {$name} resolves to {$fqcn}, which does not exist";
            }
        }

        $this->assertSame([], $unresolved, "Unresolved class references (add the missing `use`):\n  ".implode("\n  ", $unresolved));
    }

    /** @return list<string> */
    private function phpFilesUnderApp(): array
    {
        $files = [];
        $tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        foreach ($tree as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  list<mixed>  $tokens
     * @return array{0: string, 1: array<string, string>}  namespace, and alias => FQCN
     */
    private function readNamespaceAndImports(array $tokens): array
    {
        $namespace = '';
        $imports = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = trim($this->readName($tokens, $i + 1), '\\');
            }

            // Only top-level imports. A closure's `use (...)` and a trait's `use X;`
            // both reach here; the former stops at `(`, and the latter is harmless
            // because a trait name that resolves is exactly what we want to confirm.
            if ($tokens[$i][0] === T_USE && $namespace !== '') {
                $clause = $this->readName($tokens, $i + 1, allowAs: true);

                if ($clause === '') {
                    continue;
                }

                if (str_contains($clause, ' as ')) {
                    [$fqcn, $alias] = array_map('trim', explode(' as ', $clause, 2));
                } else {
                    $fqcn = trim($clause, '\\');
                    $alias = str_contains($fqcn, '\\') ? substr(strrchr($fqcn, '\\'), 1) : $fqcn;
                }

                if ($alias !== '') {
                    $imports[strtolower($alias)] = ltrim($fqcn, '\\');
                }
            }
        }

        return [$namespace, $imports];
    }

    /**
     * Reads a (possibly qualified) name starting at $i, skipping whitespace.
     *
     * @param  list<mixed>  $tokens
     */
    private function readName(array $tokens, int $i, bool $allowAs = false): string
    {
        $name = '';
        $count = count($tokens);

        for ($j = $i; $j < $count; $j++) {
            $token = $tokens[$j];

            if (! is_array($token)) {
                break;
            }

            if ($token[0] === T_WHITESPACE) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];

                continue;
            }

            if ($allowAs && $token[0] === T_AS) {
                $name .= ' as ';

                continue;
            }

            // `use function` / `use const` import symbols, not classes.
            if ($allowAs && in_array($token[0], [T_FUNCTION, T_CONST], true)) {
                return '';
            }

            break;
        }

        return $name;
    }

    /**
     * Names standing where only a class can stand: `X::`, `X $var` (type hint or
     * catch), `new X`, `instanceof X`, `extends X`, `implements X`.
     *
     * @param  list<mixed>  $tokens
     * @return list<array{0: string, 1: int}>
     */
    private function classPositionNames(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if (! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            if (in_array(strtolower($token[1]), self::NOT_CLASSES, true)) {
                continue;
            }

            $next = $this->significant($tokens, $i + 1, 1);
            $previous = $this->significant($tokens, $i - 1, -1);

            $inClassPosition = (is_array($next) && in_array($next[0], [T_DOUBLE_COLON, T_VARIABLE], true))
                || (is_array($previous) && in_array($previous[0], [T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS], true));

            if ($inClassPosition) {
                $found[] = [$token[1], $token[2]];
            }
        }

        return $found;
    }

    /** @param  list<mixed>  $tokens */
    private function significant(array $tokens, int $from, int $step): mixed
    {
        for ($i = $from; $i >= 0 && $i < count($tokens); $i += $step) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    /** @param  array<string, string>  $imports */
    private function resolve(string $name, string $namespace, array $imports): string
    {
        if ($name === '') {
            return '';
        }

        if ($name[0] === '\\') {
            return ltrim($name, '\\');
        }

        $head = explode('\\', $name)[0];

        if (isset($imports[strtolower($head)])) {
            return $imports[strtolower($head)].substr($name, strlen($head));
        }

        // PHP falls back to the global namespace for functions, never for classes,
        // so an unimported name really does mean the current namespace.
        return $namespace !== '' ? $namespace.'\\'.$name : $name;
    }
}
