<?php

namespace App\Console\Commands\RetailPos;

use App\Models\Company;
use App\Models\User;
use App\Services\Cms\CmsContentImportService;
use Illuminate\Console\Command;

class ImportWebsiteContentCommand extends Command
{
    protected $signature = 'cms:import-website-content {file} {--dry-run} {--update-existing} {--publish} {--company=}';
    protected $description = 'Import a validated RetailPOS website content manifest as CMS drafts.';

    public function handle(CmsContentImportService $importer): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') { $this->error('Provide a readable JSON manifest file.'); return self::FAILURE; }
        $manifest = json_decode((string) file_get_contents($path), true);
        if (! is_array($manifest)) { $this->error('The manifest is not valid JSON.'); return self::FAILURE; }
        $company = Company::query()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->oldest('id')->first();
        if (! $company) { $this->error('No company was found.'); return self::FAILURE; }
        $actor = User::query()->where('company_id', $company->id)->where('role', 'administrator')->first();
        try { $result = $importer->import($company, $actor, $manifest, (bool) $this->option('dry-run'), (bool) $this->option('update-existing'), (bool) $this->option('publish')); } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
        $this->table(['Created', 'Updated', 'Skipped', 'Warnings', 'Failed'], [array_values($result)]);
        return self::SUCCESS;
    }
}
