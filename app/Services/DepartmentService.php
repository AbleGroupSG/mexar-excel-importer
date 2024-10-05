<?php 


namespace App\Services;


class DepartmentService extends BaseService
{
    public function getDepartments()
    {
        $res = $this->request('/api/v1/departments', 'get');
        return $res;
    }
}