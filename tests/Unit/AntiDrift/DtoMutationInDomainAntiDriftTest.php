<?php

declare(strict_types=1);

namespace Tests\Unit\AntiDrift;

use PHPUnit\Framework\TestCase;

/**
 * DTO.md: DTO must be treated as immutable.
 *
 * Phase-1 enforcement (safe): forbid property assignment to public DTO fields
 * inside domain / repository code paths.
 *
 * Note: We intentionally do NOT scan app/DTO (constructors/hydrators) and tests
 * (already covered by TestDtoImmutabilityAntiDriftTest).
 */
final class DtoMutationInDomainAntiDriftTest extends TestCase
{
    public function test_domain_code_must_not_mutate_dto_public_properties(): void
    {
        $root = dirname(__DIR__, 3); // project root

        $dtoPublicProps = $this->collectPublicDtoPropertyNames($root . '/app/DTO');
        $this->assertNotSame([], $dtoPublicProps, 'No DTO public properties discovered; anti-drift guard is misconfigured.');

        $scanRoots = [
            $root . '/app/Trade/Watchlist',
            $root . '/app/Repositories',
        ];

        $paths = [];
        foreach ($scanRoots as $dir) {
            $paths = array_merge($paths, $this->collectPhpFiles($dir));
        }

        // Scope: enforce immutability only for Watchlist domain code (docs/DTO.md scope).
        // Avoid false positives from other modules that legitimately mutate non-DTO structs.
        $paths = array_values(array_filter($paths, function (string $p): bool {
            return (bool) preg_match('/(\/|\\\\)Watchlist(\/|\\\\)/', $p)
                || (stripos($p, 'Scorecard') !== false);
        }));

        $hits = $this->findPropertyAssignments($paths, $dtoPublicProps);

        $this->assertSame([], $hits, "Found DTO property mutations in domain code. DTO must be immutable; build a new DTO / use array input instead.\n\n" . $this->formatHits($hits));
    }

    /** @return array<string,true> */
    private function collectPublicDtoPropertyNames(string $dtoDir): array
    {
        $names = [];
        foreach ($this->collectPhpFiles($dtoDir) as $path) {
            foreach ($this->discoverClassesInFile($path) as $fqcn) {
                if (!class_exists($fqcn)) {
                    continue;
                }

                $rc = new \ReflectionClass($fqcn);
                foreach ($rc->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                    if ($p->isStatic()) {
                        continue;
                    }
                    $names[$p->getName()] = true;
                }
            }
        }

        // A small deny-list to avoid noisy false positives for extremely common names.
        // If a DTO ever exposes one of these publicly, prefer refactor to private + getter.
        unset($names['id'], $names['code'], $names['name'], $names['type']);

        return $names;
    }

    /** @return string[] */
    private function discoverClassesInFile(string $path): array
    {
        $src = file_get_contents($path);
        if (!is_string($src) || $src === '') {
            return [];
        }

        $tokens = token_get_all($src);
        $ns = '';
        $classes = [];

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            if (is_array($t) && $t[0] === T_NAMESPACE) {
                $ns = '';
                $i++;
                while ($i < $count) {
                    $t2 = $tokens[$i];
                    // PHP 7.4 does not define T_NAME_QUALIFIED (introduced in PHP 8.0).
                    // Keep parsing compatible by conditionally accepting it when available.
                    if (is_array($t2) && (
                        $t2[0] === T_STRING
                        || (defined('T_NAME_QUALIFIED') && $t2[0] === T_NAME_QUALIFIED)
                        || $t2[0] === T_NS_SEPARATOR
                    )) {
                        $ns .= $t2[1];
                    } elseif ($t2 === ';' || $t2 === '{') {
                        break;
                    }
                    $i++;
                }
                $ns = trim($ns, "\\ ");
            }

            if (is_array($t) && $t[0] === T_CLASS) {
                // Skip anonymous classes: "new class { ... }" has T_CLASS but no name token.
                $name = null;
                $j = $i + 1;
                while ($j < $count) {
                    $tj = $tokens[$j];
                    if (is_array($tj) && $tj[0] === T_STRING) {
                        $name = $tj[1];
                        break;
                    }
                    if ($tj === '{' || $tj === '(') {
                        break;
                    }
                    $j++;
                }

                if (is_string($name) && $name !== '') {
                    $classes[] = $ns !== '' ? ($ns . '\\' . $name) : $name;
                }
            }
        }

        return $classes;
    }

    /**
     * Token-based detection to avoid false positives in comments/strings.
     *
     * @param string[] $paths
     * @param array<string,true> $dtoProps
     * @return array<int,array{file:string,line:int,prop:string}>
     */
    private function findPropertyAssignments(array $paths, array $dtoProps): array
    {
        $hits = [];
        foreach ($paths as $path) {
            $src = file_get_contents($path);
            if (!is_string($src) || $src === '') {
                continue;
            }

            $tokens = token_get_all($src);
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $t = $tokens[$i];
                if (!is_array($t) || $t[0] !== T_OBJECT_OPERATOR) {
                    continue;
                }

                // next meaningful token should be property name (T_STRING)
                $j = $this->skipWhitespace($tokens, $i + 1);
                if ($j >= $count) {
                    continue;
                }
                $nameTok = $tokens[$j];
                if (!is_array($nameTok) || $nameTok[0] !== T_STRING) {
                    continue;
                }

                $prop = $nameTok[1];
                if (!isset($dtoProps[$prop])) {
                    continue;
                }

                // look ahead for '=' assignment (not '==', '=>', '+=', etc.)
                $k = $this->skipWhitespace($tokens, $j + 1);
                if ($k >= $count) {
                    continue;
                }

                $next = $tokens[$k];
                if ($next === '=') {
                    $hits[] = [
                        'file' => $path,
                        'line' => is_array($nameTok) ? $nameTok[2] : 0,
                        'prop' => $prop,
                    ];
                }
            }
        }

        return $hits;
    }

    /** @param array<int,mixed> $tokens */
    private function skipWhitespace(array $tokens, int $i): int
    {
        $count = count($tokens);
        while ($i < $count) {
            $t = $tokens[$i];
            if (is_array($t) && $t[0] === T_WHITESPACE) {
                $i++;
                continue;
            }
            return $i;
        }
        return $i;
    }

    /** @return string[] */
    private function collectPhpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /** @param array<int,array{file:string,line:int,prop:string}> $hits */
    private function formatHits(array $hits): string
    {
        if ($hits === []) {
            return '';
        }

        $out = [];
        $max = 25;
        foreach (array_slice($hits, 0, $max) as $h) {
            $out[] = $h['file'] . ':' . $h['line'] . '  ->' . $h['prop'] . ' = ...';
        }
        if (count($hits) > $max) {
            $out[] = '... and ' . (count($hits) - $max) . ' more';
        }
        return implode("\n", $out);
    }
}
