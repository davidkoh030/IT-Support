<?php

return [

    // Display-only defaults; storage stays UTC (config/app.php timezone).
    'default_timezone' => env('ITSM_DEFAULT_TIMEZONE', 'Asia/Singapore'),
    'default_currency' => env('ITSM_DEFAULT_CURRENCY', 'SGD'),

    /*
    |--------------------------------------------------------------------------
    | Access/lifecycle request templates (section 8)
    |--------------------------------------------------------------------------
    |
    | Defines the checklist tasks and approval steps created automatically
    | when a coordinator raises a Joiner/Transfer/Leaver/External access/
    | Site mobilisation/Site demobilisation service request. Editable here
    | by an administrator with code access; there is no GUI template editor
    | in this pilot (see REQUIREMENTS.md).
    */
    'request_templates' => [
        'joiner' => [
            'category' => 'New starter',
            'checklist' => [
                ['name' => 'Create account and mailbox', 'assigned_role' => 'it_agent'],
                ['name' => 'Assign standard software bundle', 'assigned_role' => 'it_agent'],
                ['name' => 'Allocate device(s)', 'assigned_role' => 'it_agent'],
                ['name' => 'Grant project/site access', 'assigned_role' => 'it_agent'],
                ['name' => 'Manager sign-off on day 1 readiness', 'assigned_role' => 'manager'],
            ],
            'approvals' => ['manager'],
        ],
        'transfer' => [
            'category' => 'Transfer',
            'checklist' => [
                ['name' => 'Review outgoing project access', 'assigned_role' => 'it_agent'],
                ['name' => 'Grant incoming project access', 'assigned_role' => 'it_agent'],
                ['name' => 'Reassign allocated assets', 'assigned_role' => 'it_agent'],
            ],
            'approvals' => ['manager'],
        ],
        'leaver' => [
            'category' => 'Leaver',
            'checklist' => [
                ['name' => 'Revoke account/session access', 'assigned_role' => 'it_agent'],
                ['name' => 'Recover software licences', 'assigned_role' => 'it_agent'],
                ['name' => 'Collect issued assets', 'assigned_role' => 'it_agent'],
                ['name' => 'Confirm data handover complete', 'assigned_role' => 'manager'],
            ],
            'approvals' => ['manager'],
        ],
        'external_access' => [
            'category' => 'External access',
            'checklist' => [
                ['name' => 'Verify sponsor and business purpose', 'assigned_role' => 'it_agent'],
                ['name' => 'Provision scoped access with expiry', 'assigned_role' => 'it_agent'],
                ['name' => 'Confirm revocation at expiry', 'assigned_role' => 'it_agent'],
            ],
            'approvals' => ['resource_owner'],
        ],
        'site_mobilisation' => [
            'category' => 'Site setup',
            'checklist' => [
                ['name' => 'Confirm ISP/temporary connectivity install date', 'assigned_role' => 'it_agent'],
                ['name' => 'Deploy firewall/Wi-Fi', 'assigned_role' => 'it_agent'],
                ['name' => 'Deliver printers and UPS', 'assigned_role' => 'it_agent'],
                ['name' => 'Allocate site assets', 'assigned_role' => 'it_agent'],
                ['name' => 'IT readiness check', 'assigned_role' => 'it_manager'],
                ['name' => 'Site manager sign-off', 'assigned_role' => 'manager'],
            ],
            'approvals' => ['manager'],
        ],
        'site_demobilisation' => [
            'category' => 'Site closure',
            'checklist' => [
                ['name' => 'Return or redeploy assets', 'assigned_role' => 'it_agent'],
                ['name' => 'Cancel circuits/subscriptions', 'assigned_role' => 'it_agent'],
                ['name' => 'Revoke project access', 'assigned_role' => 'it_agent'],
                ['name' => 'Recover licences', 'assigned_role' => 'it_agent'],
                ['name' => 'Archive records per retention policy', 'assigned_role' => 'it_manager'],
            ],
            'approvals' => ['manager'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention (section 10) - demo values only, admin-editable here.
    |--------------------------------------------------------------------------
    */
    'retention_days' => [
        'ticket' => 365 * 3,
        'audit_log' => 365 * 7,
        'attachment' => 365 * 3,
    ],
];
