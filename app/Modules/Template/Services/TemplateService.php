<?php

namespace App\Modules\Template\Services;

use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TemplateService
{
    /**
     * Get paginated templates with filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Template::query();

        // Filter by channel
        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Search by name or slug
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Find template by ID or slug
     */
    public function find(string $idOrSlug): ?Template
    {
        // Try UUID first
        if (Str::isUuid($idOrSlug)) {
            return Template::find($idOrSlug);
        }

        // Try slug
        return Template::where('slug', $idOrSlug)->first();
    }

    /**
     * Create new template
     */
    public function create(array $data): Template
    {
        // Extract and store variables
        $template = Template::create($data);
        
        // Auto-extract variables from content
        $variables = $template->extractVariables();
        $template->update(['variables' => $variables]);

        return $template->fresh();
    }

    /**
     * Update template
     */
    public function update(Template $template, array $data): Template
    {
        $template->update($data);

        // Re-extract variables if content changed
        if (isset($data['content']) || isset($data['subject'])) {
            $variables = $template->extractVariables();
            $template->update(['variables' => $variables]);
        }

        return $template->fresh();
    }

    /**
     * Delete template
     */
    public function delete(Template $template): bool
    {
        // Check if template is in use
        $usageCount = $template->notifications()->count();
        
        if ($usageCount > 0) {
            throw new \RuntimeException(
                "Cannot delete template. It is currently used by {$usageCount} notification(s).",
                409
            );
        }

        return $template->delete();
    }

    /**
     * Render template with variables
     */
    public function render(Template $template, array $variables): array
    {
        return $template->render($variables);
    }
}
