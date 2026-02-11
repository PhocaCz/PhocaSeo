<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Ai;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * AI Client Interface for LLM providers
 *
 * @since  6.0.0
 */
interface AiClientInterface
{
    /**
     * Generate an SEO-optimized meta title
     *
     * @param   string  $content      The content to analyze
     * @param   string  $keyword      The focus keyword
     * @param   array   $constraints  Optional rules/constraints (min_length, max_length, ideal_length)
     *
     * @return  string  Generated meta title
     *
     * @since   6.0.0
     */
    public function generateMetaTitle(string $content, string $keyword, array $constraints = []): string;

    /**
     * Generate an SEO-optimized meta description
     *
     * @param   string  $content      The content to analyze
     * @param   string  $keyword      The focus keyword
     * @param   array   $constraints  Optional rules/constraints (min_length, max_length, ideal_length)
     *
     * @return  string  Generated meta description
     *
     * @since   6.0.0
     */
    public function generateMetaDescription(string $content, string $keyword, array $constraints = []): string;

    /**
     * Generate SEO-optimized meta keywords
     *
     * @param   string  $content      The content to analyze
     * @param   string  $keyword      The focus keyword
     * @param   array   $constraints  Optional rules/constraints (max_count, max_length)
     *
     * @return  string  Generated meta keywords (comma separated)
     *
     * @since   6.0.0
     */
    public function generateMetaKeywords(string $content, string $keyword, array $constraints = []): string;

    /**
     * Generate content for a specific Schema.org field
     *
     * @param   string  $content  The content to analyze
     * @param   string  $field    The field name (e.g. 'recipeInstructions')
     * @param   string  $type     The schema type (e.g. 'Recipe')
     * @param   string  $source   Optional source context (e.g. image filename)
     *
     * @return  string  Generated schema data
     *
     * @since   6.0.0
     */
    public function generateSchemaField(string $content, string $field, string $type, string $source = ''): string;

    /**
     * Analyze content for SEO quality
     *
     * @param   string  $content      The content to analyze
     * @param   string  $keyword      The focus keyword
     * @param   array   $constraints  Optional rules/constraints
     *
     * @return  array  Analysis results
     *
     * @since   6.0.0
     */
    public function analyzeContent(string $content, string $keyword, array $constraints = []): array;

    /**
     * Check if the client is properly configured
     *
     * @return  bool  True if API key is set and valid
     *
     * @since   6.0.0
     */
    public function isConfigured(): bool;

    /**
     * Get the provider name
     *
     * @return  string  Provider identifier
     *
     * @since   6.0.0
     */
    public function getProviderName(): string;
}
