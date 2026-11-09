<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class DeviceConfigsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('device_configs');
        $this->setDisplayField('device_id');
        $this->setPrimaryKey('id');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('device_id')
            ->maxLength('device_id', 50)
            ->requirePresence('device_id', 'create')
            ->notEmptyString('device_id');

        $validator
            ->numeric('target_rpm')
            ->requirePresence('target_rpm', 'create')
            ->notEmptyString('target_rpm');

        $validator
            ->numeric('kp')
            ->requirePresence('kp', 'create')
            ->notEmptyString('kp');

        $validator
            ->numeric('ki')
            ->requirePresence('ki', 'create')
            ->notEmptyString('ki');

        $validator
            ->numeric('kd')
            ->requirePresence('kd', 'create')
            ->notEmptyString('kd');

        $validator
            ->integer('pid_samp')
            ->requirePresence('pid_samp', 'create')
            ->notEmptyString('pid_samp');

        $validator
            ->boolean('fan_pid_enabled')
            ->requirePresence('fan_pid_enabled', 'create')
            ->notEmptyString('fan_pid_enabled');

        return $validator;
    }
}