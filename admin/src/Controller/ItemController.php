<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Component\PhocaSeo\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\MVC\Controller\FormController;
use Phoca\Component\PhocaSeo\Administrator\Ai\AiClientFactory;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\SeoRulesHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkScannerHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\LinkSuggestionHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoContentHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\KeywordVariationsHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class ItemController extends FormController
{
    /**
     * Method to add a new record.
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    public function add()
    {
        /** @var \Joomla\CMS\Application\AdministratorApplication $app */
        $app = Factory::getApplication();
        $refId = $app->input->getInt('ref_id', 0);
        $refContext = $app->input->get('ref_context', '', 'string');

        if ($refId > 0 && $refContext != '') {
            $app->setUserState('com_phocaseo.add.ref_id', $refId);
            $app->setUserState('com_phocaseo.add.ref_context', $refContext);
            $app->enqueueMessage("Stashed: $refContext / $refId", 'notice');
        }

        return parent::add();
    }

    protected function allowAdd($data = [])
    {
        return parent::allowAdd($data);
    }

    /**
     * Method to check if you can edit an existing record.
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    /**
     * AJAX Method to analyze content
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function analyze()
    {

        // Check for request forgery
        $this->checkToken();

        $app = Factory::getApplication();
        $input = $app->input;
        $context = $input->get('context', '', 'string');
        $itemId = $input->getInt('item_id', 0);
        $keyword = $input->get('keyword', '', 'string');

        // Prepare response
        $result = [
            'success' => false,
            'message' => '',
            'data'    => []
        ];

        try {
            if (empty($context)) {
                if (empty($context)) $missing[] = 'context';
                //if (empty($itemId)) $missing[] = 'item_id';
                throw new \Exception(Text::_('COM_PHOCASEO_ERROR_INVALID_ITEM') . ' (Missing: ' . implode(', ', $missing) . ')');

            }

            // Get Content (Prioritize passed content for real-time analysis)
            $content = $input->get('content', '', 'string');
            if (empty($content) && isset($itemId) && $itemId > 0) {
                $content = PhocaSeoContentHelper::getContent($context, $itemId);
            }

            if (empty($content)) {
                throw new \Exception(Text::_('COM_PHOCASEO_ERROR_NO_CONTENT'));
            }

            // Get AI Client
            $client = AiClientFactory::create();

            // Analyze
            $analysis = $client->analyzeContent($content, $keyword);


            $result['success'] = true;
            $result['data'] = $analysis;

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * AJAX Method to generate metadata
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function generate()
    {
        // Check for request forgery
        $this->checkToken();

        $app = Factory::getApplication();
        $input = $app->input;
        $context = $input->get('context', '', 'string');
        $itemId = $input->getInt('item_id', 0);
        $keyword = $input->get('keyword', '', 'string');
        $type = $input->get('type', 'title', 'string'); // 'title', 'description', 'keywords'

        // Prepare response
        $result = [
            'success' => false,
            'message' => '',
            'data'    => ''
        ];

        try {
            // Get Content (Prioritize passed content for unsaved items)
            $content = $input->get('content', '', 'string');
            if (empty($content)) {
                $content = PhocaSeoContentHelper::getContent($context, $itemId);
            }

            if (empty($content)) {
                throw new \Exception('Article content is empty. Please type some text in the editor first.');
            }

            // Get Rules/Constraints
            $rules = SeoRulesHelper::getRules();
            $constraints = [];

            if ($type === 'title' && isset($rules['parameters']['title'])) {
                $constraints = $rules['parameters']['title'];
            } elseif ($type === 'description' && isset($rules['parameters']['description'])) {
                $constraints = $rules['parameters']['description'];
            } elseif ($type === 'keywords' && isset($rules['parameters']['keywords'])) {
                $constraints = $rules['parameters']['keywords'];
            }

            // Get AI Client
            $client = AiClientFactory::create();

            switch ($type) {
                case 'title':
                    $generated = $client->generateMetaTitle($content, $keyword, $constraints);
                    break;
                case 'description':
                    $generated = $client->generateMetaDescription($content, $keyword, $constraints);
                    break;
                case 'keywords':
                    $generated = $client->generateMetaKeywords($content, $keyword, $constraints);
                    break;
                case 'schema':
                    $field = $input->get('field', '', 'string');
                    $sType = $input->get('schema_type', 'Article', 'string');
                    $iSrc  = $input->get('image_src', '', 'string');
                    $generated = $client->generateSchemaField($content, $field, $sType, $iSrc);
                    break;
                default:
                    throw new \Exception('Invalid generation type');
            }

            // Clean up AI output (stripping literal quotes and extra noise)
            $generated = trim((string)$generated, " \t\n\r\0\x0B\"'");
            $generated = str_replace(['\"', "\'"], ['"', "'"], $generated);
            $generated = trim($generated, "\"'");

            $result['success'] = true;
            $result['data'] = $generated;

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * AJAX Method to get link statistics for an item
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function getLinkStats(): void
    {
        $this->checkToken('get');

        $app = Factory::getApplication();
        $input = $app->input;
        $itemId = $input->getInt('item_id', 0);
        $context = $input->getString('context', 'com_content.article');

        $result = [
            'success' => true,
            'data' => [
                'inbound_count' => 0
            ]
        ];

        if ($itemId > 0) {
            $result['data']['inbound_count'] = LinkSuggestionHelper::countInboundLinks($itemId, $context);
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * AJAX Method to get internal linking suggestions
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function getSuggestions(): void
    {
        $this->checkToken('get');

        $app = Factory::getApplication();
        $input = $app->input;
        $keyword = $input->getString('keyword', '');
        $excludeId = $input->getInt('exclude_id', 0);
        $context = $input->getString('context', 'com_content.article');

        $result = [
            'success' => true,
            'data' => []
        ];

        if (strlen($keyword) >= 3) {
            $result['data'] = LinkSuggestionHelper::getSuggestions($keyword, $excludeId, $context, 10);
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * AJAX Method to get social preview data
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function getSocialPreview(): void
    {
        $this->checkToken();

        $app = Factory::getApplication();
        $input = $app->input;
        $itemId = $input->getInt('item_id', 0);
        $context = $input->getString('context', 'com_content.article');

        $result = [
            'success' => true,
            'data' => [
                'image' => ''
            ]
        ];

        // Collect overrides for real-time preview
        $overrides = [];
        $intro = $input->get('image_intro', '', 'string');
        $full = $input->get('image_full', '', 'string');

        if ($intro || $full) {
             $overrides['images'] = ['image_intro' => $intro, 'image_fulltext' => $full];
        }
        $content = $input->get('content', '', 'string');
        if ($content) {
            $overrides['content'] = $content;
        }
        $ogImage = $input->get('og_image', '', 'string');
        if ($ogImage) {
            $overrides['og_image'] = $ogImage;
        }

        $result['data']['image'] = PhocaSeoHelper::getSocialImage($context, $itemId, $overrides);

        echo json_encode($result);
        $app->close();
    }

    /**
    * Get keyword variations for a given keyword (AJAX)
    * Called from JavaScript via: task=item.getKeywordVariations
    *
    * @return  void
    * @since   6.0.0
    */
    public function getKeywordVariations()
    {
        // Verify this is an AJAX request
        if (!$this->app->input->get('format') === 'json') {
            throw new \Exception('Invalid request format');
        }

        try {
            $keyword = $this->app->input->post->getString('keyword', '');
            $language = $this->app->input->post->getString('language', '');


            if (empty($keyword)) {
                throw new \Exception('Keyword is required');
            }


            // Use the KeywordVariationsHelper to generate variations
            $variations = KeywordVariationsHelper::generate(
                $keyword,
                $language
            );


            $cleanVariations = [];
            foreach ($variations as $variant) {
                $cleanVariations[] = $variant;

                $asciiVariant = OutputFilter::stringURLSafe($variant);

                if ($asciiVariant !== $variant) {
                    $cleanVariations[] = $asciiVariant;
                }
            }

            $variations = array_values(array_unique($cleanVariations));


            // Return success response
            echo new JsonResponse([
                'variations' => $variations,
                'strategy' => KeywordVariationsHelper::getStrategy(),
                'language' => $language ?: KeywordVariationsHelper::getLanguageCode(),
                'count' => count($variations)
            ]);
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $this->app->close();
    }

    public function getLanguagePatterns() {
        if (!$this->app->input->get('format') === 'json') {
            throw new \Exception('Invalid request format');
        }

        try {
            $language = $this->app->input->post->getString('language', '');

            // Use the helper to load the specific language patterns
            $patterns = KeywordVariationsHelper::getLanguagePatterns($language);

            echo new JsonResponse([
                'transition_words' => $patterns['transition_words'] ?? [],
                'passive_voice'    => $patterns['passive_voice'] ?? [],
                'language'         => $language
            ]);
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $this->app->close();
    }


    /**
    * MODIFIED: Existing analyzeContent method to support variations
    * Update your existing analyzeContent method to include variations in AI prompt
    *
    * @return  void
    * @since   6.0.0
    */
    public function analyzeContent()
    {
        if ($this->app->input->get('format') !== 'json') {
            throw new \Exception('Invalid request format');
        }

        try {
            $content = $this->app->input->post->getString('content', '');
            $keyword = $this->app->input->post->getString('keyword', '');
            $keywordVariations = $this->app->input->post->getString('keyword_variations', ''); // NEW

            if (empty($content) || empty($keyword)) {
                throw new \Exception(Text::_('COM_PHOCASEO_ERROR_MISSING_CONTENT_KEYWORD'));
            }

            $aiClient = AiClientFactory::create();

            if (!$aiClient->isConfigured()) {
                throw new \Exception(Text::_('COM_PHOCASEO_ERROR_AI_NOT_CONFIGURED'));
            }

            // Build constraints for AI
            $constraints = [
                'keyword_variations' => $keywordVariations, // NEW: Pass variations to AI
            ];

            $analysis = $aiClient->analyzeContent($content, $keyword, $constraints);

            echo new JsonResponse($analysis);
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $this->app->close();
    }

}

