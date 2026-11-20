<?php

return [
    'POST /users' => [
        'summary' => 'Create a user',
        'description' => 'Creates a user account with a unique email, locale, optional is_active and role ids the actor may assign (never super-admin unless the actor is one), the required personal_data card (individual: first/last name; company: company name; contacts, addresses) that gives the name, and an optional employment block (sites, superiors, roles, functions, competences, dates; IBAN needs users.manageCompensation). An active user needs a business function (422). A generated password is sent in a queued welcome email and must be changed at first login. Returns 201 with the user. Requires users.create.',
    ],
    'POST /users/bulk-activation' => [
        'summary' => 'Activate or deactivate many users',
        'description' => 'Sets is_active on up to 200 users (ids), each through the same rules as a single edit: users the actor may not edit, the actor deactivating themselves, active users without a business function and users whose termination date has passed are refused individually without blocking the others. Returns updated (ids) and failed (id, name, message). Requires users.update.',
    ],
    'GET /users/for-select' => [
        'summary' => 'Search users for a selector',
        'description' => 'Returns a paginated list of users (service accounts excluded) ordered by name, each as id, label (name), subtitle (email), avatar_url and meta with the primary operational site. search matches name or email; operational_site_id keeps assignable members of that site; competence_category_ids keeps users competent for those product categories; offset and limit (default 25, max 100); ids[] appends already selected users; include_total false skips the count. Any authenticated user.',
    ],
    'GET /users/{user}' => [
        'summary' => 'Get a user',
        'description' => 'Returns the user with roles, avatar, personal-data card (contacts and addresses), employment profile, managed business functions and preferences, plus the user\'s permissions on it. Requires users.view.',
    ],
    'PUT /users/{user}' => [
        'summary' => 'Update a user',
        'description' => 'Partial update: only the fields sent change (email, locale, is_active, roles, password with password_confirmation, personal_data, employment; employment null removes the employment profile). A password set on another user must be changed by them at next login. The last super-admin cannot lose the role; a user whose termination date has passed is saved inactive; an active user must keep a business function (422). Fields locked for the actor are refused (422). Only a super-admin edits a super-admin. Returns the user with permissions. Requires users.update.',
    ],
    'DELETE /users/{user}' => [
        'summary' => 'Delete a user',
        'description' => 'Deletes the user and answers 204 with no content. Refused when deleting oneself or a super-admin as non-super-admin (403), or the last super-admin (422); 409 when the user is the operator of leads, the supervisor of opportunities or appears in stock movements. Requires users.delete.',
    ],
    'POST /users/{user}/avatar' => [
        'summary' => 'Upload a user\'s avatar',
        'description' => 'Uploads an image (multipart field avatar: jpeg, png, gif or webp) as the user\'s avatar, replacing any previous one. Returns the updated user. Requires users.update; only a super-admin changes a super-admin.',
    ],
    'DELETE /users/{user}/avatar' => [
        'summary' => 'Remove a user\'s avatar',
        'description' => 'Deletes the user\'s avatar, if any, and returns the updated user. Requires users.update; only a super-admin changes a super-admin.',
    ],
    'GET /users/{user}/employment-contracts' => [
        'summary' => 'List the contract history of a user',
        'description' => 'Returns the user\'s contractual conditions, latest start first, each with start date, derived end date (day before the next one starts), is_current, relationship type, company, contract type, working time, expiry, change reason, and salary only for users holding users.manageCompensation. Empty when the user has no employment profile. Requires users.view and the contracts field visible to the actor.',
    ],
    'POST /users/{user}/employment-contracts' => [
        'summary' => 'Add a contract condition to a user',
        'description' => 'Adds a condition starting on starts_on (unique per user) with relationship_type (employee, self_employed, intern, other); employees also need an active contract_type_id and working_time (full_time, part_time). Optional company, expiry (not before the start), salary_amount with salary_period (monthly, yearly, hourly; needs users.manageCompensation, else 403) and change reason. The condition in force is copied onto the employment profile. Returns 201 with the condition; 404 when the user has no employment profile. Requires users.update and the contracts field editable.',
    ],
    'DELETE /users/{user}/employment-contracts/{employmentContract}' => [
        'summary' => 'Delete a contract condition of a user',
        'description' => 'Deletes the condition and realigns the employment profile with the one now in force; answers 204 with no content. Only the latest condition can be deleted (409 otherwise); 404 when it belongs to another user. Requires users.update and the contracts field editable.',
    ],
    'POST /users/{user}/resend-welcome' => [
        'summary' => 'Resend the welcome email with a new password',
        'description' => 'Replaces the user\'s password with a newly generated one, forces a password change at next login, revokes all their sessions and emails the new password. 422 when the user is inactive, a service account or the actor themselves. Returns success with null data. Requires users.update.',
    ],
];
