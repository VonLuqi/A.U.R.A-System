<?php

use Illuminate\Support\Facades\Route;

/*
| API session-aware (Opção A — Etapa C §1.1).
| Included here (not via bootstrap `api:`) so routes inherit the `web`
| middleware stack (session + CSRF). Must be registered BEFORE the SPA
| catch-all so /api/* is not swallowed.
*/
Route::prefix('api')->group(base_path('routes/api.php'));

// SPA catch-all: never swallow /api/* or /storage/* (private files / missing API → 404).
Route::view('/{any?}', 'app')->where('any', '^(?!api(?:/|$)|storage(?:/|$)).*');
