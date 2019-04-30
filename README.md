[![CircleCI](https://circleci.com/gh/t3n/EventSourcing.GoogleFireStoreEventStore.svg?style=svg)](https://circleci.com/gh/t3n/EventSourcing.GoogleFireStoreEventStore) [![Latest Stable Version](https://poser.pugx.org/t3n/eventsourcing-googlefirestoreeventstore/v/stable)](https://packagist.org/packages/t3n/eventsourcing-googlefirestoreeventstore) [![Total Downloads](https://poser.pugx.org/t3n/eventsourcing-googlefirestoreeventstore/downloads)](https://packagist.org/packages/t3n/eventsourcing-googlefirestoreeventstore)

# t3n.EventSourcing.GoogleFireStoreEventStore

[Neos.EventSourcing](https://github.com/neos/Neos.EventSourcing) adapter for the [Google Cloud Firestore](https://cloud.google.com/firestore/)

## Installation

Install via composer:

    composer require t3n/eventsourcing-googlefirestoreeventstore

### Configuration

Example configuration:

```yaml
Neos:
  EventSourcing:
    EventStore:
      stores:
        'default':
          storage: 't3n\EventSourcing\GoogleFireStoreEventStore\GoogleFireStoreEventStorage'
          storageOptions:
            baseDocumentId: 'some_namespace'
            fireStoreConfig:
              keyFilePath: '%FLOW_PATH_ROOT%Configuration/keyfile.json'
```

You can obtain a Google Cloud key at https://console.cloud.google.com/apis/credentials/serviceaccountkey
