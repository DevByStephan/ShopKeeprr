<?php

namespace App\Services\Updates;

use App\Models\Module;
use App\Support\Version;
use App\Support\VersionConstraint;
use Throwable;

class ModuleCompatibility
{
    /**
     * @return list<array{severity: string, module: string|null, message: string}>
     */
    public function inspect(string $targetVersion, string $targetModuleApi): array
    {
        $warnings = [];

        try {
            $modules = Module::query()->orderBy('name')->get();
        } catch (Throwable) {
            return $warnings;
        }

        foreach ($modules as $module) {
            $enabled = (bool) $module->is_enabled;
            $severity = $enabled ? 'blocking' : 'notice';
            $manifest = $this->manifest($module);

            if ($manifest === null) {
                $warnings[] = [
                    'severity' => $enabled ? 'warning' : 'notice',
                    'module' => $module->slug,
                    'message' => "{$module->name} is missing its module.json manifest, so its compatibility cannot be checked.",
                ];

                continue;
            }

            $requires = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : [];
            // shopkeeprr is the current key; nexus and nexuscart remain
            // accepted so modules published before the rebrand keep working.
            $nexus = (string) ($requires['shopkeeprr'] ?? $requires['nexus'] ?? $requires['nexuscart'] ?? '');
            $moduleApi = isset($requires['module_api']) ? (string) $requires['module_api'] : '';

            if ($nexus !== '' && ! VersionConstraint::satisfies($targetVersion, $nexus)) {
                $warnings[] = [
                    'severity' => $severity,
                    'module' => $module->slug,
                    'message' => "{$module->name} {$module->version} supports ShopKeeprr {$nexus}, which does not include {$targetVersion}.",
                ];
            }

            if ($moduleApi !== '' && ! VersionConstraint::satisfies($targetModuleApi, $moduleApi)) {
                $warnings[] = [
                    'severity' => $severity,
                    'module' => $module->slug,
                    'message' => "{$module->name} requires module API {$moduleApi}; this release provides {$targetModuleApi}.",
                ];
            }

            if ($enabled && $nexus === '') {
                $warnings[] = [
                    'severity' => 'warning',
                    'module' => $module->slug,
                    'message' => "{$module->name} does not declare which ShopKeeprr versions it was tested with.",
                ];
            }

            if ($enabled && $moduleApi === '' && $targetModuleApi !== Version::moduleApi()) {
                $warnings[] = [
                    'severity' => 'warning',
                    'module' => $module->slug,
                    'message' => "{$module->name} does not declare a module API version, and this release changes it.",
                ];
            }
        }

        if ($targetModuleApi !== Version::moduleApi()) {
            $warnings[] = [
                'severity' => 'warning',
                'module' => null,
                'message' => 'This release changes the module API from '.Version::moduleApi()." to {$targetModuleApi}. Enabled modules may need updates.",
            ];
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manifest(Module $module): ?array
    {
        $path = base_path(trim((string) $module->path, '/').'/module.json');

        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);

            if (is_array($data)) {
                return $data;
            }
        }

        return is_array($module->meta) ? $module->meta : null;
    }
}
