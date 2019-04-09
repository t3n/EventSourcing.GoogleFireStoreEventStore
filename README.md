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
