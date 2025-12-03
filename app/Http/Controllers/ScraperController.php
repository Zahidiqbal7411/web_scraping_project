<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\Testimonial;

class ScraperController extends Controller
{
   
    public function showTestimonials()
    {
        return view('testimonials.index');
    }

    public function getTestimonials()
    {
        $testimonials = Testimonial::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'count' => $testimonials->count(),
            'testimonials' => $testimonials
        ]);
    }

    public function syncTestimonials()
    {
        try {
            // Get existing testimonials to compare
            $existingTestimonials = Testimonial::orderBy('id')->get()->toArray();
            
            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);
            
            $response = $client->request('GET', 'https://webtechfusion.pk/');
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);
            $testimonials = [];
            
            // Dynamically scrape the large testimonial image
            $largeImage = null;
            $imageSelectors = [
                '.techex--tm-image img',
                '.testimonial-image img',
                '.testimonial-section img',
                '.testimonial-main-image img',
                'img[src*="Testimonial-Image"]',
            ];
            
            foreach ($imageSelectors as $imgSelector) {
                try {
                    $imgElement = $crawler->filter($imgSelector);
                    if ($imgElement->count() > 0) {
                        $src = $imgElement->first()->attr('src');
                        if (!empty($src)) {
                            if (strpos($src, 'http') !== 0) {
                                $src = 'https://webtechfusion.pk/' . ltrim($src, '/');
                            }
                            $largeImage = $src;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Fallback to the actual WebTechFusion image if dynamic scraping failed
            if (empty($largeImage)) {
                $largeImage = 'https://webtechfusion.pk/wp-content/uploads/2024/03/Testimonial-Image.png';
            }

            // Target the specific testimonial structure from WebTechFusion
            $selectors = [
                '.techex--tn-single',  // Specific to the website
                '.slick-slide .techex--tn-single',
                '.swiper-slide',
                '.testimonial-item',
            ];

            foreach ($selectors as $selector) {
                try {
                    $items = $crawler->filter($selector);
                    
                    if ($items->count() > 0) {
                        $testimonials = $items->each(function (Crawler $node) use ($largeImage) {
                            $testimonial = [];

                            // Extract testimonial text using specific selector
                            try {
                                $textElement = $node->filter('.techex--tn-dis p, .testimonial-text p, p');
                                if ($textElement->count() > 0) {
                                    $text = trim($textElement->first()->text());
                                    if (!empty($text) && strlen($text) > 10) {
                                        $testimonial['quote'] = $text;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['quote'] = null;
                            }

                            // Extract author name using specific selector
                            try {
                                $nameElement = $node->filter('.techex--tn-name, h4, h3, .author-name');
                                if ($nameElement->count() > 0) {
                                    $nameText = trim($nameElement->first()->text());
                                    if (!empty($nameText) && strlen($nameText) < 100) {
                                        $testimonial['author_name'] = $nameText;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['author_name'] = null;
                            }

                            // Extract author position/title using specific selector
                            try {
                                $titleElement = $node->filter('.techex--tn-title, span.title, .author-position');
                                if ($titleElement->count() > 0) {
                                    $titleText = trim($titleElement->first()->text());
                                    if (!empty($titleText) && $titleText !== ($testimonial['author_name'] ?? '')) {
                                        $testimonial['author_position'] = $titleText;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['author_position'] = null;
                            }

                            // Extract small avatar image
                            try {
                                $avatarImg = $node->filter('.techex--t-thumb img, img.avatar, img');
                                if ($avatarImg->count() > 0) {
                                    $src = $avatarImg->first()->attr('src');
                                    if (!empty($src)) {
                                        if (strpos($src, 'http') !== 0) {
                                            $src = 'https://webtechfusion.pk/' . ltrim($src, '/');
                                        }
                                        $testimonial['author_image'] = $src;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['author_image'] = null;
                            }

                            // Add the dynamically scraped large image to each testimonial
                            $testimonial['large_image'] = $largeImage;

                            // Only return if we found at least a quote or author name
                            if (!empty($testimonial['quote']) || !empty($testimonial['author_name'])) {
                                return $testimonial;
                            }
                            
                            return null;
                        });

                        // Filter out null values
                        $testimonials = array_filter($testimonials);
                        
                        if (!empty($testimonials)) {
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Check if data has changed
            $newTestimonials = array_values($testimonials);
            $hasChanges = $this->testimonialsHaveChanged($existingTestimonials, $newTestimonials);

            if (!$hasChanges && count($existingTestimonials) > 0) {
                // No changes detected
                return response()->json([
                    'success' => true,
                    'updated' => false,
                    'message' => 'No new updates found',
                    'count' => count($existingTestimonials)
                ]);
            }

            // Clear existing testimonials and insert new ones
            Testimonial::truncate();
            
            foreach ($testimonials as $testimonialData) {
                Testimonial::create($testimonialData);
            }

            return response()->json([
                'success' => true,
                'updated' => true,
                'message' => 'Testimonials synced successfully',
                'count' => count($testimonials)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function testimonialsHaveChanged($existing, $new)
    {
        // If counts differ, data has changed
        if (count($existing) !== count($new)) {
            return true;
        }

        // Compare testimonial content (excluding timestamps)
        foreach ($existing as $index => $existingItem) {
            if (!isset($new[$index])) {
                return true;
            }

            $newItem = $new[$index];
            
            // Compare key fields
            if (($existingItem['quote'] ?? '') !== ($newItem['quote'] ?? '') ||
                ($existingItem['author_name'] ?? '') !== ($newItem['author_name'] ?? '') ||
                ($existingItem['author_position'] ?? '') !== ($newItem['author_position'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    public function scrapeTestimonials()
    {
        try {
            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);
            
            $response = $client->request('GET', 'https://webtechfusion.pk/');
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);
            $testimonials = [];
            
            // Dynamically scrape the large testimonial image
            $largeImage = null;
            $imageSelectors = [
                '.techex--tm-image img',
                '.testimonial-image img',
                '.testimonial-section img',
                '.testimonial-main-image img',
                'img[src*="Testimonial-Image"]',
            ];
            
            foreach ($imageSelectors as $imgSelector) {
                try {
                    $imgElement = $crawler->filter($imgSelector);
                    if ($imgElement->count() > 0) {
                        $src = $imgElement->first()->attr('src');
                        if (!empty($src)) {
                            if (strpos($src, 'http') !== 0) {
                                $src = 'https://webtechfusion.pk/' . ltrim($src, '/');
                            }
                            $largeImage = $src;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Fallback to the actual WebTechFusion image if dynamic scraping failed
            if (empty($largeImage)) {
                $largeImage = 'https://webtechfusion.pk/wp-content/uploads/2024/03/Testimonial-Image.png';
            }

            // Target the specific testimonial structure from WebTechFusion
            $selectors = [
                '.techex--tn-single',  // Specific to the website
                '.slick-slide .techex--tn-single',
                '.swiper-slide',
                '.testimonial-item',
            ];

            foreach ($selectors as $selector) {
                try {
                    $items = $crawler->filter($selector);
                    
                    if ($items->count() > 0) {
                        $testimonials = $items->each(function (Crawler $node) use ($largeImage) {
                            $testimonial = [];

                            // Extract testimonial text using specific selector
                            try {
                                $textElement = $node->filter('.techex--tn-dis p, .testimonial-text p, p');
                                if ($textElement->count() > 0) {
                                    $text = trim($textElement->first()->text());
                                    if (!empty($text) && strlen($text) > 10) {
                                        $testimonial['quote'] = $text;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['quote'] = null;
                            }

                            // Extract author name using specific selector
                            try {
                                $nameElement = $node->filter('.techex--tn-name, h4, h3, .author-name');
                                if ($nameElement->count() > 0) {
                                    $nameText = trim($nameElement->first()->text());
                                    if (!empty($nameText) && strlen($nameText) < 100) {
                                        $testimonial['author_name'] = $nameText;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['author_name'] = null;
                            }

                            // Extract author position/title using specific selector
                            try {
                                $titleElement = $node->filter('.techex--tn-title, span.title, .author-position');
                                if ($titleElement->count() > 0) {
                                    $titleText = trim($titleElement->first()->text());
                                    if (!empty($titleText) && $titleText !== ($testimonial['author_name'] ?? '')) {
                                        $testimonial['author_position'] = $titleText;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['author_position'] = null;
                            }

                            // Extract small avatar image
                            try {
                                $avatarImg = $node->filter('.techex--t-thumb img, img.avatar, img');
                                if ($avatarImg->count() > 0) {
                                    $src = $avatarImg->first()->attr('src');
                                    if (!empty($src)) {
                                        if (strpos($src, 'http') !== 0) {
                                            $src = 'https://webtechfusion.pk/' . ltrim($src, '/');
                                        }
                                        $testimonial['author_image'] = $src;
                                    }
                                }
                            } catch (\Exception $e) {
                                $testimonial['author_image'] = null;
                            }

                            // Add the dynamically scraped large image to each testimonial
                            $testimonial['large_image'] = $largeImage;

                            // Only return if we found at least a quote or author name
                            if (!empty($testimonial['quote']) || !empty($testimonial['author_name'])) {
                                return $testimonial;
                            }
                            
                            return null;
                        });

                        // Filter out null values
                        $testimonials = array_filter($testimonials);
                        
                        if (!empty($testimonials)) {
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'count' => count($testimonials),
                'testimonials' => array_values($testimonials),
                'large_image' => $largeImage
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
