<?php

namespace App\Modules\Template\Controllers;

use App\Modules\Template\Exceptions\TemplateException;
use App\Modules\Template\Requests\StoreTemplateRequest;
use App\Modules\Template\Resources\TemplateResource;
use App\Modules\Template\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class TemplateController
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    /**
     * List all templates
     */
    #[OA\Get(path: '/api/v1/templates', summary: 'List templates', tags: ['Templates'])]
    #[OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email', 'push']), description: 'Filter by channel')]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Filter by active status')]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Search in name and slug')]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at'))]
    #[OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Items per page')]
    #[OA\Response(response: 200, description: 'Paginated list of templates')]
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
    #[OA\Get(path: '/api/v1/templates/{id}', summary: 'Get template by ID or slug', tags: ['Templates'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), description: 'Template UUID or slug')]
    #[OA\Response(response: 200, description: 'Template details')]
    #[OA\Response(response: 404, description: 'Template not found')]
    public function show(string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            throw TemplateException::notFound($id);
        }

        return response()->json([
            'success' => true,
            'data' => new TemplateResource($template),
        ]);
    }

    /**
     * Create new template
     */
    #[OA\Post(path: '/api/v1/templates', summary: 'Create template', tags: ['Templates'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'channel', 'content'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Welcome Email', description: 'Template name'),
                new OA\Property(property: 'slug', type: 'string', maxLength: 255, description: 'Optional. Unique slug; auto-generated from name if omitted'),
                new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email', 'push'], description: 'Channel (sms, email, push)'),
                new OA\Property(property: 'content', type: 'string', example: 'Hello {{name}}, welcome!', description: 'Template body; use {{variable}} for placeholders'),
                new OA\Property(property: 'subject', type: 'string', maxLength: 255, example: 'Welcome {{name}}', description: 'Optional. Subject (e.g. for email); supports {{variable}}'),
                new OA\Property(property: 'description', type: 'string', description: 'Optional. Human-readable description'),
                new OA\Property(property: 'is_active', type: 'boolean', description: 'Optional. Default true'),
            ],
            example: [
                'name' => 'Welcome Email',
                'channel' => 'email',
                'content' => 'Hello {{name}}, welcome to our service!',
                'subject' => 'Welcome {{name}}',
                'description' => 'Sent after signup',
                'is_active' => true,
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Template created')]
    #[OA\Response(response: 409, description: 'Duplicate slug')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        // No try-catch needed - global handler catches QueryException for duplicate slug
        $template = $this->templateService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully.',
            'data' => new TemplateResource($template),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update template
     */
    #[OA\Put(path: '/api/v1/templates/{id}', summary: 'Update template', tags: ['Templates'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), description: 'Template UUID or slug')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255),
                new OA\Property(property: 'slug', type: 'string', maxLength: 255, description: 'Unique; must differ from other templates'),
                new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email', 'push']),
                new OA\Property(property: 'content', type: 'string', description: 'Template body with {{variable}} placeholders'),
                new OA\Property(property: 'subject', type: 'string', maxLength: 255),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ],
            example: [
                'name' => 'Welcome Email v2',
                'content' => 'Hi {{name}}, thanks for joining!',
                'subject' => 'Welcome {{name}}',
                'is_active' => true,
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Template updated')]
    #[OA\Response(response: 404, description: 'Template not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(StoreTemplateRequest $request, string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            throw TemplateException::notFound($id);
        }

        // No try-catch needed - global handler catches exceptions
        $updated = $this->templateService->update($template, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully.',
            'data' => new TemplateResource($updated),
        ]);
    }

    /**
     * Delete template
     */
    #[OA\Delete(path: '/api/v1/templates/{id}', summary: 'Delete template', tags: ['Templates'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), description: 'Template UUID or slug')]
    #[OA\Response(response: 200, description: 'Template deleted')]
    #[OA\Response(response: 404, description: 'Template not found')]
    #[OA\Response(response: 409, description: 'Template in use')]
    public function destroy(string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            throw TemplateException::notFound($id);
        }

        // No try-catch needed - TemplateException::inUse() is caught by global handler
        $this->templateService->delete($template);

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully.',
        ]);
    }

    /**
     * Render template with variables
     */
    #[OA\Post(path: '/api/v1/templates/{id}/render', summary: 'Render template with variables', tags: ['Templates'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), description: 'Template UUID or slug')]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'variables',
                    type: 'object',
                    description: 'Key-value map for {{placeholder}} substitution in content and subject',
                    example: ['name' => 'John', 'product' => 'Widget']
                ),
            ],
            example: ['variables' => ['name' => 'John', 'product' => 'Widget']]
        )
    )]
    #[OA\Response(response: 200, description: 'Rendered content and subject')]
    #[OA\Response(response: 404, description: 'Template not found')]
    public function render(Request $request, string $id): JsonResponse
    {
        $template = $this->templateService->find($id);

        if (!$template) {
            throw TemplateException::notFound($id);
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
