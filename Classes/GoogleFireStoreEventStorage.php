<?php

declare(strict_types=1);

namespace t3n\EventSourcing\GoogleFireStoreEventStore;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FieldValue;
use Google\Cloud\Firestore\FirestoreClient;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Neos\EventSourcing\EventStore\EventStream;
use Neos\EventSourcing\EventStore\Exception\ConcurrencyException;
use Neos\EventSourcing\EventStore\ExpectedVersion;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\EventSourcing\EventStore\WritableEvents;

final class GoogleFireStoreEventStorage implements EventStorageInterface
{
    /**
     * @var string
     */
    private $baseCollectionName = 'event-store';

    /**
     * @var string
     */
    private $baseDocumentId = 'default';

    /**
     * @var mixed[]
     */
    private $fireStoreConfig = [];

    /**
     * @var DocumentReference|null
     */
    private $baseDocumentCache;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options)
    {
        if (array_key_exists('baseCollectionName', $options)) {
            $this->baseCollectionName = $options['baseCollectionName'];
        }
        if (array_key_exists('baseDocumentId', $options)) {
            $this->baseDocumentId = $options['baseDocumentId'];
        }
        if (array_key_exists('fireStoreConfig', $options)) {
            $this->fireStoreConfig = $options['fireStoreConfig'];
        }
    }

    public function load(StreamName $streamName, int $minimumSequenceNumber = 0): EventStream
    {
        $baseDocument = $this->baseDocument();
        $events = $baseDocument->collection('events');
        $query = $events->orderBy('sequenceNumber');

        if (! $streamName->isVirtualStream()) {
            $query = $query->where('stream', '=', (string) $streamName);
        } elseif ($streamName->isCategoryStream()) {
            $query = $query
                ->where('stream', '>', $streamName->getCategoryName())
                ->where('stream', '<=', $streamName->getCategoryName() . chr(127));
        } elseif (! $streamName->isAllStream()) {
            throw new \InvalidArgumentException(sprintf('Unsupported virtual stream name "%s"', $streamName), 1551440062);
        }
        if ($minimumSequenceNumber > 0) {
            $query = $query->where('sequenceNumber', '>=', $minimumSequenceNumber);
        }
        return new EventStream($streamName, new EventStreamIterator($query));
    }

    public function commit(StreamName $streamName, WritableEvents $events, int $expectedVersion = ExpectedVersion::ANY): void
    {
        $firestore = $this->firestore();

        $baseDocument = $this->baseDocument();
        $eventsCollection = $baseDocument->collection('events');
        $streamsCollection = $baseDocument->collection('streams');
        $streamVersionDocument = $streamsCollection->document($streamName);
        $allVersionDocument = $streamsCollection->document('all');

        $firestore->runTransaction(function () use ($eventsCollection, $streamVersionDocument, $allVersionDocument, $streamName, $events, $expectedVersion): void {
            $streamVersion = $this->getVersion($streamVersionDocument);
            if ($expectedVersion !== ExpectedVersion::ANY && $expectedVersion !== $streamVersion) {
                throw new ConcurrencyException(sprintf('Expected version <b>%s</b>, but have <b>%s</b> on stream "%s"', $expectedVersion, $streamVersion, $streamName), 1551207397);
            }
            $allVersionNumber = $this->getVersion($allVersionDocument);

            foreach ($events as $event) {
                $eventDocument = $eventsCollection->document($event->getIdentifier());
                $correlationId = $event->getMetadata()['correlationIdentifier'] ?? null;
                $causationId = $event->getMetadata()['causationIdentifier'] ?? null;
                $eventDocument->create([
                    'sequenceNumber' => $allVersionNumber + 2,
                    'stream' => (string) $streamName,
                    'version' => $streamVersion + 1,
                    'type' => $event->getType(),
                    'payload' => json_encode($event->getData()),
                    'metadata' => json_encode($event->getMetadata()),
                    'correlationId' => $correlationId,
                    'causationId' => $causationId,
                    'recordedAt' => FieldValue::serverTimestamp(),
                ]);
            }
            $this->increaseVersion($streamVersionDocument, $streamVersion);
            $this->increaseVersion($allVersionDocument, $allVersionNumber);
        });
    }

    public function getStatus(): Result
    {
        return $this->setup();
    }

    public function setup(): Result
    {
        $result = new Result();
        try {
            $this->firestore();
        } catch (\RuntimeException $exception) {
            $result->addError(new Error($exception->getMessage(), $exception->getCode()));
            return $result;
        }

        $result->addNotice(new Notice($this->baseCollectionName, null, [], 'Base Collection'));
        $result->addNotice(new Notice($this->baseDocumentId, null, [], 'Base Document Id'));
        $baseDocument = $this->baseDocument();

        $eventsCollection = $baseDocument->collection('events');
        $streamsCollection = $baseDocument->collection('streams');
        $result->addNotice(new Notice($eventsCollection->path(), null, [], 'Events Collection'));
        $result->addNotice(new Notice($streamsCollection->path(), null, [], 'Streams Collection'));

        // TODO: verify that stream can actually be read
        return $result;
    }

    private function firestore(): FirestoreClient
    {
        try {
            return new FirestoreClient($this->fireStoreConfig);
        } catch (GoogleException $e) {
            throw new \RuntimeException('Could not connect to Google Firestore: ' . $e->getMessage(), 1551115204, $e);
        }
    }

    private function baseDocument(): DocumentReference
    {
        if ($this->baseDocumentCache === null) {
            try {
                $baseCollection = $this->firestore()->collection($this->baseCollectionName);
                $this->baseDocumentCache = $baseCollection->document($this->baseDocumentId);
            } /** @noinspection PhpRedundantCatchClauseInspection */ catch (GoogleException $e) {
                throw new \RuntimeException('Could not fetch base document: ' . $e->getMessage(), 1554817737, $e);
            }
        }
        return $this->baseDocumentCache;
    }

    private function getVersion(DocumentReference $documentReference): int
    {
        $versionSnapshot = $documentReference->snapshot();
        if (! $versionSnapshot->exists()) {
            return -1;
        }
        return (int) $versionSnapshot->get('version');
    }

    private function increaseVersion(DocumentReference $documentReference, int $currentVersion): void
    {
        if ($currentVersion === -1) {
            $documentReference->create(['version' => 0]);
        } else {
            $documentReference->set(['version' => $currentVersion + 1]);
        }
    }
}
