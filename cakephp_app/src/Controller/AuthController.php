<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\AppController;
use Firebase\JWT\JWT;
use Cake\Core\Configure;

class AuthController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();

        // Non usiamo template .php, rispondiamo sempre JSON
        $this->autoRender = false;
    }

    /**
     * POST /auth/login
     * Body JSON: { "username": "...", "password": "..." }
     */
    public function login()
    {
      //  $this->request->allowMethod(['post']);

        $data = $this->request->getData();

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // 🔒 Utente finto di test (poi puoi metterlo a DB)
        $validUser = 'mahdi';
        $validPass = '123456';

        if ($username !== $validUser || $password !== $validPass) {
            $body = json_encode([
                'success' => false,
                'error' => 'Credenziali non valide',
            ]);

            $this->response = $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody($body);

            return $this->response;
        }

        // ✅ Credenziali ok → genero JWT
        $now = time();
        $expire = $now + 3600; // 1 ora

        $payload = [
            'sub' => $username,
            'iat' => $now,
            'exp' => $expire,
        ];

        $secret = (string)Configure::read('Security.jwtKey');

        $jwt = JWT::encode($payload, $secret, 'HS256');

        $body = json_encode([
            'success'    => true,
            'token'      => $jwt,
            'expires_in' => 3600,
        ]);

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody($body);

        return $this->response;
    }
}
