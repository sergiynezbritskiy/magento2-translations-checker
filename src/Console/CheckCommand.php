<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Console;

use SergiyNezbritskiy\TranslationsChecker\Collector\PhraseCollectorInterface;
use SergiyNezbritskiy\TranslationsChecker\Dictionary\DictionaryReader;
use SergiyNezbritskiy\TranslationsChecker\Exception\UsageException;
use SergiyNezbritskiy\TranslationsChecker\Model\LanguageResult;
use SergiyNezbritskiy\TranslationsChecker\Model\TranslationChecker;
use SergiyNezbritskiy\TranslationsChecker\Report\ConsoleReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends Command
{
    /**
     * Phrases are written in this locale, so its dictionary is identical to the phrase list by
     * construction and is never checked.
     */
    public const SOURCE_LOCALE = 'en_US';

    public function __construct(private readonly PhraseCollectorInterface $collector)
    {
        parent::__construct('check');
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'Checks that every phrase collected from a Magento 2 module (the way '
                . '`bin/magento i18n:collect-phrases` does) is translated in each i18n dictionary.',
            )
            ->addOption(
                'source',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory to collect phrases from (what you would pass to i18n:collect-phrases).',
            )
            ->addOption(
                'translations',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory with <locale>.csv dictionaries. Defaults to <source>/i18n.',
            )
            ->addOption(
                'languages',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Locale(s) to check, e.g. --languages=ru_RU --languages=uk_UA or --languages=ru_RU,uk_UA.',
                [],
            )
            ->addOption(
                'allow-identical',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Phrase whose translation may be identical to the phrase itself (e.g. "ID" or "(+ %1)"), '
                . 'so such a row is not reported as untranslated. The row must still exist. Repeat the '
                . 'option for each phrase; values are not split on commas.',
                [],
            )
            ->addOption('colors', null, InputOption::VALUE_NEGATABLE, 'Use colors in screen output.', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->setDecorated((bool) $input->getOption('colors'));

        $source = $this->resolveDirectory($input->getOption('source'), '--source');
        $translations = $this->resolveDirectory(
            $input->getOption('translations') ?? $source . '/i18n',
            '--translations',
        );
        /** @var string[] $rawLanguages */
        $rawLanguages = $input->getOption('languages');
        $languages = $this->resolveLanguages($rawLanguages);
        /** @var string[] $allowedIdentical */
        $allowedIdentical = $input->getOption('allow-identical');

        $phrases = $this->collector->collect($source);
        $reader = new DictionaryReader();
        $checker = new TranslationChecker($allowedIdentical);
        $results = [];
        $skippedLocales = [];

        foreach ($languages as $locale) {
            if ($locale === self::SOURCE_LOCALE) {
                $skippedLocales[] = $locale;

                continue;
            }

            $dictionary = $reader->read($translations . '/' . $locale . '.csv');
            $results[] = $checker->check($locale, $phrases, $dictionary);
        }

        (new ConsoleReporter())->render($output, $source, count($phrases), $results, $skippedLocales);

        return $this->determineExitCode($results);
    }

    private function resolveDirectory(mixed $path, string $option): string
    {
        if (!is_string($path) || $path === '') {
            throw new UsageException(sprintf('%s is required.', $option));
        }

        $resolved = realpath($path);

        if ($resolved === false || !is_dir($resolved)) {
            throw new UsageException(sprintf('%s: directory "%s" does not exist.', $option, $path));
        }

        return rtrim($resolved, '/');
    }

    /**
     * @param string[] $rawLanguages
     * @return list<string>
     */
    private function resolveLanguages(array $rawLanguages): array
    {
        $languages = [];

        foreach ($rawLanguages as $rawLanguage) {
            foreach (explode(',', $rawLanguage) as $language) {
                $language = trim($language);

                if ($language !== '' && !in_array($language, $languages, true)) {
                    $languages[] = $language;
                }
            }
        }

        if ($languages === []) {
            throw new UsageException('At least one --languages=<locale> is required (e.g. --languages=uk_UA).');
        }

        return $languages;
    }

    /**
     * @param LanguageResult[] $results
     */
    private function determineExitCode(array $results): int
    {
        foreach ($results as $result) {
            if ($result->hasViolations()) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
