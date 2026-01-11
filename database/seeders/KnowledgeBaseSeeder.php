<?php

namespace Database\Seeders;

use App\Services\AI\RAG\KnowledgeBaseService;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(KnowledgeBaseService $kb): void
    {
        $knowledge = [
            // General Benchmarks
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'E-commerce Brazil Benchmarks 2024',
                'content' => 'Average conversion rate: 1.5-2.5%. Average order value: R$ 350-450. Cart abandonment rate: 65-75%. Repeat purchase rate: 20-30%. Customer acquisition cost (CAC): R$ 50-150. Email open rate: 15-25%. Email click rate: 2-5%.',
            ],
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'title' => 'Fashion E-commerce Brazil Benchmarks',
                'content' => 'Conversion rate: 2-3%. Average order value: R$ 180-280. Return rate: 15-25%. Strong seasonality on Mothers Day, Black Friday. Repeat purchase rate: 35-45%. Average items per order: 2-3. Mobile traffic: 70-80%.',
            ],
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'title' => 'Electronics E-commerce Brazil Benchmarks',
                'content' => 'Conversion rate: 1-1.5%. Average order value: R$ 800-1500. Decision cycle: 7-15 days. Reviews importance: very high. Price comparison: 85% of buyers. Return rate: 5-10%. Warranty questions: common.',
            ],
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'title' => 'Beauty E-commerce Brazil Benchmarks',
                'content' => 'Conversion rate: 2.5-3.5%. Average order value: R$ 120-200. Repeat purchase rate: 40-50%. Subscription potential: high. Reviews and tutorials: essential. Samples and gifts: effective conversion strategy.',
            ],
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'Food E-commerce Brazil Benchmarks',
                'content' => 'Conversion rate: 3-5%. Average order value: R$ 80-150. Order frequency: weekly/biweekly. Delivery speed: critical factor. Repeat purchase rate: 50-70%. Subscription boxes: growing trend.',
            ],

            // Proven Strategies
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Conditional Free Shipping',
                'content' => 'Offering free shipping above a minimum value increases average order value by 15-30%. Ideal value: 10-20% above current average order value. Displaying a progress bar "R$ X more for free shipping" increases conversion by 8-12%. Test different thresholds to optimize.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Abandoned Cart Recovery',
                'content' => 'Email within 1 hour: 15-20% recovery rate. Sequence of 3 emails in 72h: additional 5-10% recovery. Offering coupon in 3rd email: 30% increase in sequence conversion. Personalize with product images. WhatsApp recovery: 25-35% open rate.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Urgency and Scarcity',
                'content' => 'Low stock counter (<10 units) increases conversion by 10-15%. Time-limited promotions: 20-35% sales increase during period. Avoid overuse to maintain credibility. Flash sales: 4-8 hour duration optimal. Countdown timers on product pages.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Customer Reviews and Social Proof',
                'content' => 'Products with reviews convert 3-4x more. Request reviews 7-14 days after delivery. Photo reviews increase trust by 25%. Display total number of satisfied customers. User-generated content in product pages increases conversion 10-15%.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'title' => 'Fashion Cross-Selling',
                'content' => 'Suggesting complete outfits increases order value by 25-40%. "Customers also bought" converts 5-8% additional. Kits/bundles with progressive discount: 20% increase in items per order. Virtual try-on features increase conversion by 30%.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'title' => 'Size Guide and Fit',
                'content' => 'Interactive size guide reduces returns by 20-30%. Fit recommendations based on previous purchases. Include model measurements in photos. Offer free exchanges for sizing. Size prediction tools reduce return rate significantly.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'title' => 'Electronics Comparison Tools',
                'content' => 'Product comparison feature increases conversion by 15-20%. Detailed specifications table essential. Video demonstrations increase engagement 40%. Extended warranty upsell: 20-30% take rate. Technical chat support reduces abandonment.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Email Marketing Segmentation',
                'content' => 'Segmented campaigns have 14% higher open rates. VIP customers: exclusive early access. Inactive customers: win-back with special offer. Browser abandonment emails: 10-15% conversion. Birthday emails with discount: high engagement.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Loyalty and Rewards Program',
                'content' => 'Points-based programs increase repeat purchases by 25-40%. Tiered status (Bronze, Silver, Gold) motivates spending. Birthday rewards increase engagement. Referral bonuses acquire customers at 30% lower CAC. Cashback programs: 20% higher retention.',
            ],

            // Success Cases
            [
                'category' => 'case',
                'niche' => 'fashion',
                'title' => 'Case: Fashion Store +150% Sales',
                'content' => 'Store implemented: 1) Product photos with real models (+30% conversion), 2) Interactive size guide (-40% returns), 3) Free shipping above R$ 199 (+45% average order value), 4) Abandoned cart email with 10% off (+12% recovery). Result: 150% sales increase in 6 months.',
            ],
            [
                'category' => 'case',
                'niche' => 'electronics',
                'title' => 'Case: Electronics Store +80% Conversion',
                'content' => 'Store implemented: 1) Product comparison tool (+25% engagement), 2) Video reviews from customers (+35% trust), 3) Extended warranty upsell (+20% order value), 4) Live chat technical support (-30% abandonment). Result: 80% conversion increase in 4 months.',
            ],
            [
                'category' => 'case',
                'niche' => 'beauty',
                'title' => 'Case: Beauty Store +200% Retention',
                'content' => 'Store implemented: 1) Subscription box option (+50% repeat purchases), 2) Samples with every order (+25% satisfaction), 3) Tutorial videos on product pages (+40% engagement), 4) VIP program with early access (+35% loyalty). Result: 200% retention increase in 8 months.',
            ],

            // Seasonality
            [
                'category' => 'seasonality',
                'niche' => 'general',
                'title' => 'Brazil E-commerce Commercial Calendar',
                'content' => 'January: Summer clearance. February: Back to school, Carnival. March: International Womens Day. May: Mothers Day (2nd largest date). June: Valentines Day (Brazil), São João. August: Fathers Day. October: Childrens Day. November: Black Friday (largest date). December: Christmas. Preparation: stock up 60 days before major dates.',
            ],
            [
                'category' => 'seasonality',
                'niche' => 'general',
                'title' => 'Black Friday Brazil Strategies',
                'content' => 'Start promotions 1-2 weeks early (Black Week). Real discounts essential - consumers research prices. Extended hours/weekend sales. Email warming 30 days before. Stock verification critical. Server capacity planning. Post-BF clearance extends sales period.',
            ],
            [
                'category' => 'seasonality',
                'niche' => 'fashion',
                'title' => 'Fashion Seasonality',
                'content' => 'Spring/Summer collection: September-February. Fall/Winter collection: March-August. Season transition: clearance opportunity. New collection launch: 15-20% of loyal customers buy first week. Pre-season marketing: 30 days anticipation builds demand.',
            ],

            // Customer Retention
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Customer Retention Tactics',
                'content' => 'Post-purchase follow-up email sequence. Product usage tips and tutorials. Exclusive offers for repeat customers. Anniversary of first purchase celebration. Surprise and delight: unexpected gifts or upgrades. Re-engagement campaigns for dormant customers (60-90 days inactive).',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Reducing Cart Abandonment',
                'content' => 'Simplify checkout: reduce steps to 3 or less. Guest checkout option essential. Display shipping cost early. Multiple payment options (PIX, credit, boleto). Trust badges and security seals. Exit-intent popup with incentive. Save cart for logged users.',
            ],

            // Pricing Strategies
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Dynamic Pricing Strategies',
                'content' => 'Competitor price monitoring: adjust weekly. Psychological pricing: R$ 99.90 vs R$ 100. Bundle pricing: 15-20% discount on sets. Volume discounts for B2B. Flash sales for inventory clearance. Price anchoring with original price display.',
            ],
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Coupon Strategy Best Practices',
                'content' => 'First purchase discount: 10-15% optimal. Minimum order value prevents margin loss. Unique codes prevent abuse. Expiration creates urgency. Tiered discounts encourage higher spending. Track ROI per coupon campaign. Limit usage per customer.',
            ],
        ];

        foreach ($knowledge as $item) {
            $kb->add($item);
        }

        $this->command->info('Knowledge base seeded with '.count($knowledge).' entries.');
    }
}
