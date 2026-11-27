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
 * compatible version, a hint at breaking changes from the TER upload comments and the GitHub
 * commits of the versions to update across (ADR-0007), red without a version for the target major.
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
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float}
     */
    public function draft(UpgradeItemStatus $status, string $installed, ?string $target, array $versions, int $targetMajor): array
    {
        return match ($status) {
            UpgradeItemStatus::Blocker => self::note(UpgradeNoteLevel::Danger, $this->translator->trans('calculation.note.no_compatible', ['major' => $targetMajor])),
            UpgradeItemStatus::Compatible, UpgradeItemStatus::UpdateFirst, UpgradeItemStatus::Update => null === $target || $target === $installed
                ? self::note(UpgradeNoteLevel::Success, $this->translator->trans('calculation.note.latest'), 0.0)
                : $this->updateNote($installed, $target, $versions),
            default => self::note(null, null),
        };
    }

    /**
     * @param list<ExtensionVersion> $versions
     *
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float}
     */
    private function updateNote(string $installed, string $target, array $versions): array
    {
        // the versions after the installed one up to the target, oldest first
        $crossed = array_values(array_filter($versions, static fn (ExtensionVersion $v): bool => version_compare($v->version, $installed, '>') && version_compare($v->version, $target, '<=')));
        usort($crossed, static fn (ExtensionVersion $a, ExtensionVersion $b): int => version_compare($a->version, $b->version));
        $breaking = array_merge(...array_map(self::breakingChanges(...), $crossed));
        $range = ['installed' => $installed, 'target' => $target];

        if ([] !== $breaking) {
            return self::note(UpgradeNoteLevel::Warning, $this->translator->trans('calculation.note.breaking', $range)."\n\n".implode("\n", $breaking));
        }
        // a new major of the extension may break things without saying so
        if ((int) $target > (int) $installed) {
            return self::note(UpgradeNoteLevel::Warning, $this->translator->trans('calculation.note.major_update', $range));
        }
        // only claim "no breaking changes" when every version crossed tells
        $unknown = array_filter($crossed, static fn (ExtensionVersion $v): bool => !$v->hasChangelog());

        return self::note(UpgradeNoteLevel::Success, $this->translator->trans([] === $unknown ? 'calculation.note.update' : 'calculation.note.update_unchecked', $range));
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
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float}
     */
    private static function note(?UpgradeNoteLevel $level, ?string $notes, ?float $hours = null): array
    {
        return ['noteLevel' => $level, 'notes' => $notes, 'hours' => $hours];
    }
}
