<?php

it('reports the API and database as healthy', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.database', 'ok')
        ->assertJsonStructure(['data' => ['status', 'database', 'chain_id', 'contract_configured', 'time'], 'message']);
});

it('does not expose secrets in the health response', function () {
    config(['crowdchain.chain.rpc_url' => 'https://example.test/secret-key']);

    $body = $this->getJson('/api/v1/health')->getContent();

    expect($body)->not->toContain('secret-key');
});
