<?php

namespace App\Console\Commands;

use App\Support\Lifecycle\RegistryCaptureBootstrap;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class BootstrapResidentLifecycle extends Command
{
    protected $signature = 'residents:lifecycle-bootstrap
        {--apply : Write baseline history; without this flag the command is read-only}
        {--through-id= : Inclusive Resident ID cutoff; reuse the original cutoff when resuming}
        {--batch-size=250 : Residents per transaction (1-1000)}';

    protected $description = 'Explicit, resumable capture of existing registry records; never changes Residents';

    public function handle(RegistryCaptureBootstrap $bootstrap): int
    {
        $this->info($this->option('apply') ? 'APPLY' : 'DRY RUN');
        try {
            $through = $this->integerOption('through-id', true);
            $size = $this->integerOption('batch-size');
            $report = $bootstrap->inventory($through, $size);
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            if ($report['blockers']) {
                $this->error('No events written: resolve the reported schema or baseline-history blockers.');

                return self::FAILURE;
            }
            if (! $this->option('apply')) {
                return self::SUCCESS;
            }
            $cutoff = $report['through_id'];
            $this->line("Resume command: php artisan residents:lifecycle-bootstrap --apply --through-id={$cutoff} --batch-size={$size}");
            $result = $bootstrap->apply($cutoff, $size, function ($progress): void {
                $this->line("Committed batch {$progress['batches_completed']}: {$progress['created']} captures; last Resident ID {$progress['last_committed_id']}.");
            });
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Bootstrap stopped. Uncommitted work was rolled back; prior committed batches remain valid.');
            $this->error(isset($cutoff) ? "Resume with --apply --through-id={$cutoff}; do not change the original cutoff."
                : 'Check database/schema and existing baseline history before retrying.');
        }

        return self::FAILURE;
    }

    private function integerOption(string $name, bool $nullable = false): ?int
    {
        $value = $this->option($name);
        if ($nullable && $value === null) {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^[0-9]+$/D', $value)
            || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("{$name} must be a supported nonnegative integer.");
        }

        return (int) $value;
    }
}
