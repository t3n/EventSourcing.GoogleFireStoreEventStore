<?php
declare(strict_types=1);
namespace t3n\EventSourcing\GoogleFireStoreEventStore;

use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\Query;
use Neos\EventSourcing\EventStore\EventStreamIteratorInterface;
use Neos\EventSourcing\EventStore\RawEvent;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class EventStreamIterator implements EventStreamIteratorInterface
{

    /**
     * The number of documents to fetch per batch
     *
     * @var int
     */
    private const BATCH_SIZE = 100;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var int
     */
    private $currentOffset = 0;

    /**
     * @var \Generator
     */
    private $innerIterator;

    public function __construct(Query $query)
    {
        $this->query = $query->limit(self::BATCH_SIZE);
        $this->fetchBatch();
    }

    public function current(): RawEvent
    {
        $data = $this->innerIterator->current();
        /** @var Timestamp $recordedAtTimestamp */
        $recordedAtTimestamp = $data['recordedAt'];
        $payload = json_decode($data['payload'], true);
        $metadata = json_decode($data['metadata'], true);
        return new RawEvent(
            (int)$data['sequenceNumber'],
            $data['type'],
            $payload,
            $metadata,
            StreamName::fromString($data['stream']),
            (int)$data['version'],
            (string)$data->id(),
            $recordedAtTimestamp->get()
        );
    }

    public function next(): void
    {
        $this->currentOffset = $this->innerIterator->current()['sequenceNumber'];
        $this->innerIterator->next();
        if ($this->innerIterator->valid()) {
            return;
        }
        $this->fetchBatch();
    }

    /**
     * @return bool|int
     */
    public function key()
    {
        return $this->innerIterator->valid() ? $this->innerIterator->current()['sequenceNumber'] : null;
    }

    public function valid(): bool
    {
        return $this->innerIterator->valid();
    }

    public function rewind(): void
    {
        if ($this->currentOffset === 0) {
            return;
        }
        $this->currentOffset = 0;
        $this->fetchBatch();
    }

    /**
     * Fetches a batch of maximum BATCH_SIZE records
     *
     * @return void
     */
    private function fetchBatch(): void
    {
        $query = $this->query->offset($this->currentOffset);
        $this->innerIterator = $query->documents()->getIterator();
    }
}
