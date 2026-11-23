<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * One change in a site's history (e.g. "news 12.3.0 => 12.3.1"). Packages are referenced by
 * name, not by SitePackage, so the history survives the package's removal.
 */
#[ORM\Entity(repositoryClass: SiteEventRepository::class)]
#[ORM\Index(name: 'site_event_site_occurred', columns: ['site_id', 'occurred_at'])]
class SiteEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Site::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Site $site,
        #[ORM\Column(enumType: SiteEventType::class)]
        private SiteEventType $type,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $subject = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $oldValue = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $newValue = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $detail = null,
        /**
         * Translation keys and parameters of subject and detail; null for events from before
         * translations whose text no migration recognized, and for plain data like domains.
         *
         * @var array{subject?: array{key: string, params: array<string, scalar>}, detail?: array{key: string, params: array<string, scalar>}}|null
         */
        #[ORM\Column(type: Types::JSON, nullable: true)]
        private ?array $messages = null,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getType(): SiteEventType
    {
        return $this->type;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /** The subject in the reader's language, the stored English text without a translation */
    public function getSubjectMessage(): TranslatableMessage|string|null
    {
        return $this->message('subject') ?? $this->subject;
    }

    public function getDetailMessage(): TranslatableMessage|string|null
    {
        return $this->message('detail') ?? $this->detail;
    }

    /**
     * @return array{subject?: array{key: string, params: array<string, scalar>}, detail?: array{key: string, params: array<string, scalar>}}
     */
    public static function messagesOf(TranslatableMessage|string|null $subject, TranslatableMessage|string|null $detail): array
    {
        $messages = [];
        foreach (['subject' => $subject, 'detail' => $detail] as $part => $message) {
            if ($message instanceof TranslatableMessage) {
                /** @var array<string, scalar> $params */
                $params = $message->getParameters();
                $messages[$part] = ['key' => $message->getMessage(), 'params' => $params];
            }
        }

        return $messages;
    }

    private function message(string $part): ?TranslatableMessage
    {
        $message = $this->messages[$part] ?? null;

        return null !== $message ? new TranslatableMessage($message['key'], $message['params']) : null;
    }
}
