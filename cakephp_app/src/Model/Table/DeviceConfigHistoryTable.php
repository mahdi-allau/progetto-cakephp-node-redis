<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class DeviceConfigHistoryTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('device_config_history');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);
    }
}