<?php

return [
    // Maintenance-only escape hatch during legacy review, not a second normal workflow.
    'legacy_writes_enabled' => env('OPT_LEGACY_WRITES_ENABLED', false),
];
