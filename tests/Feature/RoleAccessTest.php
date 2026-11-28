<?php

/*
| RBAC matrix (docs/04-roles-permissions.md, NFR-SEC-03):
| each protected route is called as guest, donor, fundraiser and admin.
*/

$routes = [
    'admin users' => ['get', '/api/v1/admin/users', ['guest' => 401, 'donor' => 403, 'fundraiser' => 403, 'admin' => 200]],
    'admin organisations' => ['get', '/api/v1/admin/organizations', ['guest' => 401, 'donor' => 403, 'fundraiser' => 403, 'admin' => 200]],
    'admin audit log' => ['get', '/api/v1/admin/audit-logs', ['guest' => 401, 'donor' => 403, 'fundraiser' => 403, 'admin' => 200]],
    'own organisation' => ['get', '/api/v1/organization', ['guest' => 401, 'donor' => 403, 'fundraiser' => 200, 'admin' => 403]],
    'me' => ['get', '/api/v1/auth/me', ['guest' => 401, 'donor' => 200, 'fundraiser' => 200, 'admin' => 200]],
    'wallet nonce' => ['post', '/api/v1/wallet/nonce', ['guest' => 401, 'donor' => 201, 'fundraiser' => 201, 'admin' => 201]],
];

foreach ($routes as $name => [$method, $uri, $expected]) {
    foreach ($expected as $role => $status) {
        it("{$name}: {$role} gets {$status}", function () use ($method, $uri, $role, $status) {
            if ($role !== 'guest') {
                $this->actingAs($role());
            }

            $this->json($method, $uri)->assertStatus($status);
        });
    }
}
