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
        $q = $data[1] === 'individual' ? $data[4] : $data[2];
        $list = $this->getEntitiesList($q);
        if(!empty($list)) {
            foreach ($list as $item) {
                if($item['entity_type'] === 'individual' && $item['first_name'] === $data[4] && $item['last_name'] === $data[5]) {
                    return $item;
                }
                if($item['entity_type'] === 'corporate' && $item['name'] === $data[2]) {
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
        $entityType = $data[1];
        if($entityType === 'individual') {
            $payload = [
                'entity_type' => 'individual',
                'first_name' => $data[3],
                'last_name' => $data[4],
            ];
        }else {
            $payload = [
                'entity_type' => 'corporate',
                'name' => $data[2],
                'industry' => $data[3],
            ];
        }
        $response = $this->request('/api/v1/crm/entities', 'post', $payload);

        return $response['data'];
    }
}
