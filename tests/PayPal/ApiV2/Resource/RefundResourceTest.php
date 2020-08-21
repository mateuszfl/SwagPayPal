<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\Test\PayPal\ApiV2\Resource;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Swag\PayPal\PayPal\ApiV2\Resource\RefundResource;
use Swag\PayPal\Test\Helper\ServicesTrait;
use Swag\PayPal\Test\Mock\PayPal\Client\_fixtures\V2\GetRefund;

class RefundResourceTest extends TestCase
{
    use ServicesTrait;

    public function testGet(): void
    {
        $refundId = GetRefund::ID;
        $refund = $this->createResource()->get($refundId, Defaults::SALES_CHANNEL);

        static::assertSame($refundId, $refund->getId());
        static::assertSame('12.34', $refund->getSellerPayableBreakdown()->getTotalRefundedAmount()->getValue());
    }

    private function createResource(): RefundResource
    {
        return new RefundResource($this->createPayPalClientFactory());
    }
}
