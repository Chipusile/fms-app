<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['vehicles.view', 'vehicles.create', 'drivers.view', 'drivers.create'])) {
            return ApiResponse::forbidden('You do not have permission to access import templates.');
        }

        $templates = collect(config('fleet.bulk_import.templates', []))
            ->map(function (array $definition, string $resource): array {
                $columns = $definition['columns'] ?? [];
                $sampleRow = $definition['sample_row'] ?? [];

                return [
                    'resource' => $resource,
                    'label' => $definition['label'] ?? str($resource)->headline()->toString(),
                    'description' => $definition['description'] ?? null,
                    'filename' => $definition['filename'] ?? $resource.'-template.csv',
                    'columns' => $columns,
                    'sample_row' => $sampleRow,
                    'notes' => $definition['notes'] ?? [],
                    'csv_template' => $this->buildCsvTemplate($columns, $sampleRow),
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success($templates);
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, string>  $sampleRow
     */
    private function buildCsvTemplate(array $columns, array $sampleRow): string
    {
        $rows = [
            $columns,
            array_map(fn (string $column): string => (string) ($sampleRow[$column] ?? ''), $columns),
        ];

        return collect($rows)
            ->map(function (array $row): string {
                return collect($row)
                    ->map(function (string $value): string {
                        $escaped = str_replace('"', '""', $value);

                        return '"'.$escaped.'"';
                    })
                    ->implode(',');
            })
            ->implode("\n");
    }
}
