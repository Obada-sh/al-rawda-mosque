<?php

namespace App\Actions\Backups;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Backups\BackupNameRequest;
use App\Services\Backup\BackupStore;
use Illuminate\Validation\ValidationException;

/**
 * Removes a backup file from the backups folder.
 */
final class DeleteBackup
{
    public function __construct(private readonly BackupStore $store) {}

    /**
     * @param  array<string, mixed>  $input  'name' => the file name of the backup
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation backup.not_found
     */
    public function handle(array $input): void
    {
        $name = (string) BackupNameRequest::validateData($input)['name'];

        $this->store->delete($this->store->find($name) ?? throw BackupViolation::notFound());
    }
}
