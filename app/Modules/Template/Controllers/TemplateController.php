<?php

namespace App\Modules\Template\Controllers;

use App\Modules\Template\Requests\StoreTemplateRequest;
use App\Modules\Template\Resources\TemplateResource;
use App\Modules\Template\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TemplateController
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    /**
     * List all templates
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'channel',
            'is_active',
            'search',
            'sort_by',
            'sort_order',
            'per_page'
        ]);

        $templates = $this->templateService->list($filters);

        return response()->json([
            'success' => true,
            'data' => TemplateResource::collection($templates->items()),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
                'last_page' => $templates->lastPage(),
            ],
        ]);
    }

    /**
     * Get single template
     */
    public function show(string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => new TemplateResource($template),
        ]);
    }

    /**
     * Create new template
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templateService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully.',
                'data' => new TemplateResource($template),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update template
     */
    public function update(StoreTemplateRequest $request, string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $updated = $this->templateService->update($template, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully.',
                'data' => new TemplateResource($updated),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete template
     */
    public function destroy(string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->templateService->delete($template);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully.',
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Render template with variables
     */
    public function render(Request $request, string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $variables = $request->input('variables', []);
        $rendered = $this->templateService->render($template, $variables);

        return response()->json([
            'success' => true,
            'data' => [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'rendered' => $rendered,
                'variables_used' => array_keys($variables),
                'available_variables' => $template->variables,
            ],
        ]);
    }
}
