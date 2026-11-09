<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\AppController;
use Predis\Client as RedisClient;

/**
 * Messages Controller
 *
 * @property \App\Model\Table\MessagesTable $Messages
 */
class MessagesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Messages->find();
        $messages = $this->paginate($query);

        $this->set(compact('messages'));
    }

    /**
     * View method
     *
     * @param string|null $id Message id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $message = $this->Messages->get($id, contain: []);
        $this->set(compact('message'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $message = $this->Messages->newEmptyEntity();

        if ($this->request->is('post')) {
            $message = $this->Messages->patchEntity($message, $this->request->getData());

            if ($this->Messages->save($message)) {

                // ✅ Pubblica il messaggio su Redis (canale_messaggi)
                try {
                    $redis = new RedisClient(); // usa localhost:6379 di default

                    $payload = [
                        'id'       => $message->id,
                        'username' => $message->username,
                        'content'  => $message->content,
                        'created'  => $message->created?->format('Y-m-d H:i:s'),
                    ];

                    $redis->publish('canale_messaggi', json_encode($payload));
                    $this->Flash->success(__('The message has been saved.'));
                } catch (\Throwable $e) {
                    // Se Redis è down non blocchiamo l’app, solo logghiamo
                    $this->log('Errore Redis: ' . $e->getMessage(), 'error');
                    $this->Flash->success(__('The message has been saved but redis error.'));
                }

                
                return $this->redirect(['action' => 'index']);
            }

            $this->Flash->error(__('The message could not be saved. Please, try again.'));
        }

        $this->set(compact('message'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Message id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $message = $this->Messages->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $message = $this->Messages->patchEntity($message, $this->request->getData());
            if ($this->Messages->save($message)) {
                $this->Flash->success(__('The message has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The message could not be saved. Please, try again.'));
        }
        $this->set(compact('message'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Message id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $message = $this->Messages->get($id);
        if ($this->Messages->delete($message)) {
            $this->Flash->success(__('The message has been deleted.'));
        } else {
            $this->Flash->error(__('The message could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Mostra gli ultimi messaggi salvati in Redis (lista ws_messages)
     * URL: /messages/redis
     */
    public function redis()
    {
        $redis = new RedisClient(); // localhost:6379

        // ultimi 20 messaggi dalla lista ws_messages (scritti da Node.js)
        $raw = $redis->lrange('ws_messages', -20, -1);

        $redisMessages = [];
        foreach ($raw as $json) {
            $decoded = json_decode($json, true);
            if (!$decoded) {
                continue;
            }

            // username
            $username = $decoded['username'] ?? '';

            // se content è vuoto, lo costruiamo da device_name/temp/humid
            $content = $decoded['content'] ?? '';
            if ($content === '') {
                $device = $decoded['device_name'] ?? 'esp32';
                $temp   = array_key_exists('temp',  $decoded) ? (float)$decoded['temp']  : null;
                $humid  = array_key_exists('humid', $decoded) ? (float)$decoded['humid'] : null;

                $parts = [];
                $parts[] = sprintf('Device %s', $device);

                if ($temp !== null) {
                    $parts[] = sprintf('temp: %.1f°C', $temp);
                }
                if ($humid !== null) {
                    $parts[] = sprintf('humid: %.1f%%', $humid);
                }

                $content = implode(', ', $parts);
            }

            $redisMessages[] = [
                'username' => $username,
                'content'  => $content,
            ];
        }

        $this->set(compact('redisMessages'));
    }
}
