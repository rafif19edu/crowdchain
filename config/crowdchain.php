<?php

/*
|--------------------------------------------------------------------------
| CrowdChain configuration
|--------------------------------------------------------------------------
|
| Business limits come from the Decision Log (docs/00-decision-log.md).
| Anything secret or environment-specific is read from env variables, never
| hard-coded (NFR-SEC-07).
|
*/

return [

    'chain' => [
        // Sepolia = 11155111 (D-02). Hardhat local node = 31337.
        'chain_id' => (int) env('CHAIN_ID', 11155111),
        'rpc_url' => env('SEPOLIA_RPC_URL'),
        'contract_address' => env('CONTRACT_ADDRESS'),
        'deploy_block' => (int) env('CONTRACT_DEPLOY_BLOCK', 0),
        'admin_wallet' => env('ADMIN_WALLET_ADDRESS'),
        'explorer_url' => env('CHAIN_EXPLORER_URL', 'https://sepolia.etherscan.io'),
        'confirmations' => 2,
        'sync_chunk_blocks' => 5000,
    ],

    'rates' => [
        // ETH -> MYR from CoinGecko, cached (D-41).
        'coingecko_api_key' => env('COINGECKO_API_KEY'),
        'cache_minutes' => 10,
    ],

    'limits' => [
        // D-22
        'project_goal_myr' => ['min' => 100, 'max' => 100000],
        'project_duration_days' => ['min' => 7, 'max' => 90],
        'project_stages' => ['min' => 1, 'max' => 5],
        'weekly_amount_myr' => ['min' => 100, 'max' => 5000],
        'weekly_weeks' => ['min' => 4, 'max' => 12],
        'min_donation_myr' => 5,
        'active_campaigns' => ['project' => 2, 'weekly' => 1],
        // D-24, D-31, D-34, D-16
        'extension_days' => 30,
        'late_cancel_days' => 30,
        'proof_due_days' => 30,
        'expiry_reminder_days' => 30,
    ],

    'uploads' => [
        // D-47, NFR-SEC-06
        'max_kb' => 5120,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'signed_url_minutes' => 5,
    ],

];
