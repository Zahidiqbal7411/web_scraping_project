<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class ScraperController extends Controller
{
   
    public function showTestimonials()
    {
        return view('testimonials.index');
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
            
            // Fallback to local image if dynamic scraping failed
            if (empty($largeImage)) {
                $largeImage = url('images/testimonial-yellow-shirt.png');
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
