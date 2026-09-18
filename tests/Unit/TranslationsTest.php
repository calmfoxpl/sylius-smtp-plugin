<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusSmtpPlugin\Unit;

use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Cause;
use Calmfox\SyliusSmtpPlugin\Core\Diagnosis\Hint;
use Calmfox\SyliusSmtpPlugin\Core\Health\Status;
use Calmfox\SyliusSmtpPlugin\Core\Probe\Stage;
use Calmfox\SyliusSmtpPlugin\Core\Provider\ProviderCatalog;
use Calmfox\SyliusSmtpPlugin\Core\Settings\IssueCode;
use PHPUnit\Framework\TestCase;

/**
 * The two translation files, checked against the code that asks them questions.
 *
 * Every cause the core can name, every hint, every complaint about the settings and every stage
 * of the conversation has to have a sentence waiting for it in both languages. A missing one is
 * not an error anywhere — Symfony shows the key, and a shopkeeper reads
 * `calmfox_smtp.cause.auth_rejected` instead of being told their password was refused.
 *
 * The files are read with a regular expression rather than a YAML parser on purpose: that keeps
 * this suite runnable with no vendor directory, which is the whole point of the unit suite. The
 * keys are flat and dotted, so there is nothing to parse but lines.
 */
final class TranslationsTest extends TestCase
{
    /**
     * Entries that read the same in Polish: the names of mechanisms and of places, and a unit.
     * Provider region names are their own keys, which is why they appear here as sentences.
     */
    private const SAME_IN_BOTH = [
        'calmfox_smtp.form.auth_login',
        'calmfox_smtp.form.auth_plain',
        'calmfox_smtp.form.auth_crammd5',
        'calmfox_smtp.report.encryption_tls',
        'calmfox_smtp.form.port',
        'calmfox_smtp.log.ms',
        'Australia',
        'Frankfurt',
        'Oregon',
    ];

    public function testEveryCauseHasASentenceInBothLanguages(): void
    {
        foreach (Cause::ALL as $cause) {
            $this->assertTranslated('calmfox_smtp.cause.' . $cause);
            $this->assertTranslated('calmfox_smtp.detail.' . $cause);
        }
    }

    public function testEveryHintHasASentenceInBothLanguages(): void
    {
        foreach (Hint::ALL as $hint) {
            $this->assertTranslated('calmfox_smtp.hint.' . $hint);
        }
    }

    public function testEverySettingsComplaintHasASentenceInBothLanguages(): void
    {
        foreach (IssueCode::ALL as $code) {
            $this->assertTranslated('calmfox_smtp.issue.' . $code);
        }
    }

    public function testEveryStageAndStatusIsNamedInBothLanguages(): void
    {
        foreach (Stage::ALL as $stage) {
            $this->assertTranslated('calmfox_smtp.stage.' . (Stage::DONE === $stage ? 'finishing' : $stage));
        }
        foreach (Status::ALL as $status) {
            $this->assertTranslated('calmfox_smtp.status.' . $status);
        }
    }

    /**
     * The provider hints are the sentences somebody reads while typing a credential, which makes
     * them the last ones that should be left in English.
     */
    public function testEveryProviderHintIsTranslated(): void
    {
        $strings = [];
        foreach (ProviderCatalog::all() as $provider) {
            foreach ($provider->hints() as $hint) {
                $strings[] = $hint;
            }
            foreach (array_keys($provider->hostVariants) as $region) {
                $strings[] = (string) $region;
            }
        }

        self::assertNotEmpty($strings);
        foreach (array_unique($strings) as $string) {
            $this->assertTranslated($string);
        }
    }

    public function testTheTwoFilesHaveTheSameKeys(): void
    {
        $english = array_keys(self::translations('en'));
        $polish = array_keys(self::translations('pl'));

        sort($english);
        sort($polish);
        self::assertSame($english, $polish);
    }

    /** The Polish file must actually translate, not repeat the English. */
    public function testThePolishFileIsTranslated(): void
    {
        $english = self::translations('en');
        $untranslated = [];

        foreach (self::translations('pl') as $key => $value) {
            if ($value === ($english[$key] ?? null) && !\in_array($key, self::SAME_IN_BOTH, true)) {
                $untranslated[] = $key;
            }
        }

        self::assertSame([], $untranslated);
    }

    /** A placeholder dropped in translation shows the reader a literal %endpoint%. */
    public function testPlaceholdersSurviveTranslation(): void
    {
        $english = self::translations('en');

        foreach (self::translations('pl') as $key => $value) {
            preg_match_all('/%[a-z0-9_]+%/', $english[$key] ?? '', $inEnglish);
            preg_match_all('/%[a-z0-9_]+%/', $value, $inPolish);

            sort($inEnglish[0]);
            sort($inPolish[0]);
            self::assertSame($inEnglish[0], $inPolish[0], sprintf('placeholders differ for %s', $key));
        }
    }

    /** Nothing spare: a key nothing asks for is a sentence nobody will ever read. */
    public function testNoKeyIsUnused(): void
    {
        $sources = '';
        foreach (['src', 'templates', 'config'] as $directory) {
            foreach (self::filesIn(\dirname(__DIR__, 2) . '/' . $directory) as $file) {
                $sources .= (string) file_get_contents($file);
            }
        }

        // The catalogue writes its apostrophes escaped, as PHP requires; the keys do not.
        $sources = str_replace("\\'", "'", $sources);

        $unused = [];
        foreach (array_keys(self::translations('en')) as $key) {
            if (str_starts_with($key, 'calmfox_smtp.')) {
                $suffix = substr($key, \strlen('calmfox_smtp.'));
                // Sentences built from a code — cause.dns_failure and the like — are asked for
                // by their prefix, so the prefix is what to look for.
                $needle = \in_array(explode('.', $suffix)[0], ['cause', 'detail', 'hint', 'issue', 'stage', 'status'], true)
                    ? explode('.', $suffix)[0] . '.'
                    : $suffix;
                if (!str_contains($sources, $needle)) {
                    $unused[] = $key;
                }

                continue;
            }
            // A provider hint: it is its own key, so it has to appear in the catalogue.
            if (!str_contains($sources, $key)) {
                $unused[] = $key;
            }
        }

        self::assertSame([], $unused);
    }

    private function assertTranslated(string $key): void
    {
        foreach (['en', 'pl'] as $locale) {
            self::assertArrayHasKey(
                $key,
                self::translations($locale),
                sprintf('messages.%s.yaml has no entry for "%s"', $locale, mb_substr($key, 0, 70)),
            );
        }
    }

    /** @return array<string, string> */
    private static function translations(string $locale): array
    {
        static $cache = [];
        if (isset($cache[$locale])) {
            return $cache[$locale];
        }

        $path = \dirname(__DIR__, 2) . '/translations/messages.' . $locale . '.yaml';
        self::assertFileExists($path);

        $entries = [];
        foreach (file($path, \FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ('' === trim($line) || str_starts_with(trim($line), '#')) {
                continue;
            }
            if (!preg_match("/^(?<key>'(?:[^']|'')+'|[^:]+):\s*'(?<value>(?:[^']|'')*)'$/", $line, $match)) {
                self::fail(sprintf('messages.%s.yaml has a line this test cannot read: %s', $locale, $line));
            }
            $key = trim($match['key']);
            if (str_starts_with($key, "'")) {
                $key = str_replace("''", "'", substr($key, 1, -1));
            }
            $entries[$key] = str_replace("''", "'", $match['value']);
        }

        return $cache[$locale] = $entries;
    }

    /** @return list<string> */
    private static function filesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
