<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\Typo3\TerExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The notes, their color and hours a TYPO3 package row starts with: done for the latest
 * compatible version, a hint at breaking changes from the TER upload comments of the
 * versions to update across, red without a version for the target major.
 */
final readonly class UpgradeNoteDrafter
{
    /** TYPO3's "[!!!]" commit prefix and the usual words for it */
    private const string BREAKING = '/\[!!!\]|breaking|incompatib|inkompatib/i';

    private const int COMMENT_LENGTH = 300;

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param list<TerExtensionVersion> $terVersions all versions of the extension
     *
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float}
     */
    public function draft(UpgradeItemStatus $status, string $installed, ?string $target, array $terVersions, int $targetMajor): array
    {
        return match ($status) {
            UpgradeItemStatus::Blocker => self::note(UpgradeNoteLevel::Danger, $this->translator->trans('calculation.note.no_compatible', ['major' => $targetMajor])),
            UpgradeItemStatus::Compatible, UpgradeItemStatus::UpdateFirst, UpgradeItemStatus::Update => null === $target || $target === $installed
                ? self::note(UpgradeNoteLevel::Success, $this->translator->trans('calculation.note.latest'), 0.0)
                : $this->updateNote($installed, $target, $terVersions),
            default => self::note(null, null),
        };
    }

    /**
     * @param list<TerExtensionVersion> $terVersions
     *
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float}
     */
    private function updateNote(string $installed, string $target, array $terVersions): array
    {
        $breaking = $this->breakingComments($installed, $target, $terVersions);
        $range = ['installed' => $installed, 'target' => $target];

        if ([] !== $breaking) {
            return self::note(UpgradeNoteLevel::Warning, $this->translator->trans('calculation.note.breaking', $range)."\n\n".implode("\n", $breaking));
        }
        // a new major of the extension may break things without saying so
        if ((int) $target > (int) $installed) {
            return self::note(UpgradeNoteLevel::Warning, $this->translator->trans('calculation.note.major_update', $range));
        }

        return self::note(UpgradeNoteLevel::Success, $this->translator->trans('calculation.note.update', $range));
    }

    /**
     * Upload comments that mention breaking changes, of the versions after the installed one up to the target.
     *
     * @param list<TerExtensionVersion> $terVersions
     *
     * @return list<string> Markdown list items, oldest first
     */
    private function breakingComments(string $installed, string $target, array $terVersions): array
    {
        $crossed = array_filter($terVersions, static fn (TerExtensionVersion $v): bool => version_compare($v->getVersion(), $installed, '>')
            && version_compare($v->getVersion(), $target, '<=')
            && 1 === preg_match(self::BREAKING, (string) $v->getUploadComment()));
        usort($crossed, static fn (TerExtensionVersion $a, TerExtensionVersion $b): int => version_compare($a->getVersion(), $b->getVersion()));

        return array_map(static function (TerExtensionVersion $v): string {
            $comment = trim((string) preg_replace('/\s+/', ' ', (string) $v->getUploadComment()));

            return sprintf('- **%s**: %s', $v->getVersion(), mb_strlen($comment) > self::COMMENT_LENGTH ? mb_substr($comment, 0, self::COMMENT_LENGTH - 1).'…' : $comment);
        }, $crossed);
    }

    /**
     * @return array{noteLevel: ?UpgradeNoteLevel, notes: ?string, hours: ?float}
     */
    private static function note(?UpgradeNoteLevel $level, ?string $notes, ?float $hours = null): array
    {
        return ['noteLevel' => $level, 'notes' => $notes, 'hours' => $hours];
    }
}
