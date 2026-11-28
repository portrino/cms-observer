<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Dto\Typo3\ExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use App\Utility\Typo3\ExtensionUtility;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The notes, their color and hours a TYPO3 package row starts with: done for the latest
 * compatible version, red without a version for the target major, else the update as the
 * agency notes it, by what the TER upload comments and GitHub commits of the versions crossed
 * say (ADR-0007): yellow with the breaking changes they name, or for a new major of the package
 * when not every version tells; green when they name none, or for a smaller update.
 */
final readonly class UpgradeNoteDrafter
{
    private const int COMMENT_LENGTH = 300;

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param list<ExtensionVersion> $versions all versions of the extension
     *
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float, meaning: string}
     */
    public function draft(UpgradeItemStatus $status, string $installed, ?string $target, array $versions, int $targetMajor): array
    {
        return match ($status) {
            UpgradeItemStatus::Blocker => self::note(UpgradeNoteLevel::Danger, $this->translator->trans('calculation.note.no_compatible', ['major' => $targetMajor]), meaning: 'no_compatible'),
            UpgradeItemStatus::Compatible, UpgradeItemStatus::UpdateFirst, UpgradeItemStatus::Update => null === $target || $target === $installed
                ? self::note(UpgradeNoteLevel::Success, $this->translator->trans('calculation.note.latest'), 0.0, 'latest')
                : $this->updateNote($installed, $target, $versions),
            default => self::note(null, null),
        };
    }

    /**
     * @param list<ExtensionVersion> $versions
     *
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float, meaning: string}
     */
    private function updateNote(string $installed, string $target, array $versions): array
    {
        // the versions after the installed one up to the target, oldest first
        $crossed = array_values(array_filter($versions, static fn (ExtensionVersion $v): bool => version_compare($v->version, $installed, '>') && version_compare($v->version, $target, '<=')));
        usort($crossed, static fn (ExtensionVersion $a, ExtensionVersion $b): int => version_compare($a->version, $b->version));
        $breaking = array_merge(...array_map(self::breakingChanges(...), $crossed));
        // a new major of the package: a big jump, which may break things without saying so
        $bigJump = (int) $target > (int) $installed;
        // only claim "no special steps" when every version crossed tells
        $known = [] === array_filter($crossed, static fn (ExtensionVersion $v): bool => !$v->hasChangelog());

        [$level, $lines] = match (true) {
            [] !== $breaking => [UpgradeNoteLevel::Warning, ['- '.$this->translator->trans('calculation.note.breaking'), ...array_map(static fn (string $change): string => '  '.$change, $breaking)]],
            $known => [UpgradeNoteLevel::Success, [$this->translator->trans('calculation.note.no_steps'), '']],
            $bigJump => [UpgradeNoteLevel::Warning, ['- '.$this->translator->trans('calculation.note.maybe_breaking')]],
            default => [UpgradeNoteLevel::Success, []],
        };
        $header = $bigJump && UpgradeNoteLevel::Warning === $level ? [$this->translator->trans('calculation.note.big_jump'), ''] : [];
        // what the note says, whatever language it's written in: a refresh compares this
        $meaning = json_encode(['update', $bigJump, $known, $breaking], JSON_THROW_ON_ERROR);

        return self::note($level, implode("\n", [...$header, ...$lines, '- '.$this->translator->trans('calculation.note.install_test')]), meaning: $meaning);
    }

    /**
     * What a version's TER comment and GitHub commits say about breaking changes.
     *
     * @return list<string> Markdown list items
     */
    private static function breakingChanges(ExtensionVersion $version): array
    {
        $changes = [];
        if (ExtensionUtility::mentionsBreakingChange($version->uploadComment)) {
            $comment = trim((string) preg_replace('/\s+/', ' ', (string) $version->uploadComment));
            $changes[] = sprintf('- **%s**: %s', $version->version, mb_strlen($comment) > self::COMMENT_LENGTH ? mb_substr($comment, 0, self::COMMENT_LENGTH - 1).'…' : $comment);
        }
        // the rule again: commits stored by an earlier, wider one
        foreach (array_filter($version->breakingCommits ?? [], static fn (array $commit): bool => ExtensionUtility::marksBreakingCommit($commit['subject'])) as $commit) {
            // a subject is a single line; brackets would end the link text early
            $changes[] = sprintf('- **%s**: [%s](%s)', $version->version, str_replace(['[', ']'], ['\\[', '\\]'], $commit['subject']), $commit['url']);
        }

        return $changes;
    }

    /**
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float, meaning: string}
     */
    /**
     * @param string $meaning what the notes say, language-independent, for the row's preset
     *
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float, meaning: string}
     */
    private static function note(?UpgradeNoteLevel $level, ?string $notes, ?float $hours = null, string $meaning = ''): array
    {
        return ['noteLevel' => $level, 'notes' => $notes, 'hours' => $hours, 'meaning' => $meaning];
    }
}
