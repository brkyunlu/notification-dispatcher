<?php

namespace App\Modules\Template\Exceptions;

/**
 * Exception class for Template module errors
 */
class TemplateException extends \RuntimeException
{
    /**
     * Create exception for duplicate slug (name already exists)
     */
    public static function duplicateSlug(string $name): self
    {
        return new self(
            "A template with this name already exists. Please use a different name.",
            409
        );
    }

    /**
     * Create exception for template in use (cannot delete)
     */
    public static function inUse(int $count): self
    {
        return new self(
            "Cannot delete template. It is currently used by {$count} notification(s).",
            409
        );
    }

    /**
     * Create exception for template not found
     */
    public static function notFound(string $idOrSlug): self
    {
        return new self(
            "Template not found.",
            404
        );
    }
}
