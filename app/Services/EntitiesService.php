<?php

namespace App\Services;

class EntitiesService extends BaseService
{
    /**
     * find the entity from the the API response
     *
     * This function will match the following fields:
     * - entity_type
     * - first_name if entity_type is individual
     * - last_name if entity_type is individual
     * - name if entity_type is corporate
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function findOrCreateEntity(array $data): array
    {
        $q = $data['entity_type'] === 'individual' ? $data['first_name'] : $data['name'];
        $list = $this->getEntitiesList($q);
        if(!empty($list)) {
            foreach ($list as $item) {
                if(
                    $item['entity_type'] === 'individual' &&
                    $item['first_name'] === $data['first_name'] &&
                    $item['last_name'] === $data['last_name']
                ) {
                    return $item;
                }
                if(
                    $item['entity_type'] === 'corporate' &&
                    $item['name'] === $data['name']
                ) {
                    return $item;
                }
            }
        }
        return $this->createEntity($data);
    }

    /**
     * @throws \Exception|\Throwable
     */
    private function getEntitiesList(string $q): array
    {
        $res = $this->request('/api/v1/crm/entities', 'get', ['q' => $q, 'department_id' => $this->getDepartmentId()]);
        return $res['data'];
    }

    /**
     * API wrapper for create entity via API call
     *
     * @throws \Exception|\Throwable
     */
    private function createEntity(array $data):array
    {
        $entityType = $data['entity_type'];
        if($entityType === 'individual') {
            $payload = [
                'department_id' => $this->getDepartmentId(),
                'entity_type' => 'individual',
                'nationality_country_id'  => $data['country_id'] ?? null,
                'first_name' => str($data['first_name']) ?? '',
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
            ];
            $response = $this->request('/api/v1/crm/entities', 'post', $payload);
            $responseData = $this->handleResponse($response, $payload, 'Error creating entity');
            $entityId = $responseData['id'];

            if(isset($data['identity_type'])) {
                $entityIdentityPayload = [
                    'entity_id' => $responseData['id'],
                    'country_id' => $data['country_id'],
                    'identity_type' => $data['identity_type'],
                    'identity_number' => $data['identity_number'],
                    'identity_expires_at' => $data['identity_expires_at'],
                ];
                $response = $this->request("/api/v1/crm/entities/$entityId/identities", 'post', $entityIdentityPayload);
                $this->handleResponse($response, $entityIdentityPayload, "Error creating entity's identity");
            }


        }else {
            $payload = [
                'department_id' => $this->getDepartmentId(),
                'entity_type' => 'corporate',
                'name' => $data['name'],
            ];

            $response = $this->request('/api/v1/crm/entities', 'post', $payload);
            $responseData = $this->handleResponse($response, $payload, 'Error creating entity');
            $entityId = $responseData['id'];
        }

        if(!$this->isContactEmpty($data['contact_info'] ?? null)) {
            $entityContactArray = $data['contact_info'];
            foreach ($entityContactArray as $contact){
                $response = $this->request("/api/v1/crm/entities/$entityId/contacts", 'post', $contact);
                $this->handleResponse($response, $contact, "Error creating entity's contact");
                dump($response);
            }
        }
        return $responseData;
    }

    /**
     * @throws \Throwable
     */
    public function attachReferEntity(array $entityInfo, array $entitiesInfo): void
    {
        if(!isset($entityInfo['referrer'])) {
            return;
        }
        $referrer = null;
        foreach ($entitiesInfo as $entity){
            if($entity['id'] === $entityInfo['referrer']){
                $referrer = $entity;
                break;
            }
        }

        if(!$referrer) {
            logger()->error(
                'Referrer not found',
                ['entity_id' => $entityInfo['id'], 'referrer_id' => $entityInfo['referrer']]
            );
        }

        if($referrer['entity_type'] !== 'individual') {
            logger()->error(
                'Referrer is not an individual',
                ['entity_id' => $entityInfo['id'], 'referrer_id' => $entityInfo['referrer']]
            );
        }

        $referrer = $this->findOrCreateEntity($referrer);

        $payload = [
            'related_entity_id' => $referrer['id'],
            'relation_type' => 'referrer',
        ];

        $res = $this->request(
            '/api/v1/crm/entities/'.$entityInfo['id'].'/relations',
            'post',
            $payload
        );

        if(isset($res['errors'])) {
            logger()->error(
                'Error creating relation',
                ['entity_id' => $entityInfo['id'], 'referrer_id' => $entityInfo['referrer']]
            );
        }
    }

    public function mappedEntities(array $entitiesInfo):array
    {
        $processedEntities = [];
        $currentEntity = null;

        foreach ($entitiesInfo as $entityInfo) {
            if (!isset($entityInfo['id'])) {
                if ($currentEntity) {
                    $currentEntity['contact_info'][] = [
                        'contact_type' => $entityInfo['contact_type'],
                        'field1' => $entityInfo['field_1'],
                        'field2' => $entityInfo['field_2'],
                    ];
                }
                continue;
            }

            if ($currentEntity) {
                $processedEntities[] = $currentEntity;
            }

            $currentEntity = [
                'id' => $entityInfo['id'],
                'entity_type' => $entityInfo['entity_type'],
                'name' => $entityInfo['name'] ?? null,
                'first_name' => $entityInfo['first_name'] ?? null,
                'last_name' => $entityInfo['last_name'] ?? null,
                'gender' => $entityInfo['gender'] ?? null,
                'country_id' => $this->getCountryId($entityInfo['country']) ?? null,
                'identity_type' => $entityInfo['identity_type'] ?? null,
                'identity_number' => $entityInfo['identity_number'] ?? null,
                'identity_expires_at' => $entityInfo['identity_expires_at'] ?? null,
                'contact_info' => [
                    [
                        'contact_type' => $entityInfo['contact_type'] ?? null,
                        'field1' => $entityInfo['field_1'] ?? null,
                        'field2' => $entityInfo['field_2'] ?? null,
                    ],
                ],
            ];
        }
        if ($currentEntity) {
            $processedEntities[] = $currentEntity;
        }
        return $processedEntities;
    }

    private function isContactEmpty(array|null $contactInfo): bool
    {
        if ($contactInfo === null) {
            return true;
        }
        if(count($contactInfo) > 1) {
            return false;
        }
        foreach ($contactInfo[0] as $key => $value) {
            if($value) {
                return false;
            }
        }
        return true;
    }

    /**
     * @throws \Throwable
     */
    public function entityExists(array $entityInfo):bool
    {
        $payload = [
            'q' => $entityInfo['entity_type'] === 'individual' ? $entityInfo['first_name'] : $entityInfo['name'],
            'department_id' => $this->getDepartmentId(),
        ];
        $res = $this->request('/api/v1/crm/entities', 'get', $payload);
        if(empty($res['data'])) {
            return false;
        }
        foreach ($res['data'] as $entity) {
            if(
                $entity['entity_type'] === 'individual' &&
                $entity['first_name'] === $entityInfo['first_name'] &&
                $entity['last_name'] === $entityInfo['last_name']
            ) {
                return true;
            }
            if(
                $entity['entity_type'] === 'corporate' &&
                $entity['name'] === $entityInfo['name']
            ) {
                return true;
            }
        }
        return false;
    }

    public function fetchAllEntities(): array
    {
        $res = $this->request('/api/v1/crm/entities');
        return $res['data'] ?? [];
    }
}
