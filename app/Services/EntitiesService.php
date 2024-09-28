<?php

namespace App\Services;

class EntitiesService extends BaseService
{
    /**
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
        $res = $this->request('/api/v1/crm/entities', 'get', ['q' => $q]);
        return $res['data'];
    }

    /**
     * @throws \Exception|\Throwable
     */
    private function createEntity(array $data):array
    {
        $entityType = $data['entity_type'];
        if($entityType === 'individual') {
            $payload = [
                'entity_type' => 'individual',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ];
        }else {
            $payload = [
                'entity_type' => 'corporate',
                'name' => $data['name'],
            ];
        }
        $response = $this->request('/api/v1/crm/entities', 'post', $payload);

        if(isset($response['errors'])) {
            $errors = $response['errors'];
            foreach ($errors as $error) {
                logger()->error(
                    'Error creating entity',
                    ['message' => $error['message'], 'payload' => $payload],
                );
            }
        }

        return $response['data'];
    }
}
