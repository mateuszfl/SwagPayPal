<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\Util\Availability;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\PayPal\Util\Lifecycle\Method\PaymentMethodDataRegistry;

class AvailabilityService
{
    private PaymentMethodDataRegistry $paymentMethodDataRegistry;

    public function __construct(PaymentMethodDataRegistry $paymentMethodDataRegistry)
    {
        $this->paymentMethodDataRegistry = $paymentMethodDataRegistry;
    }

    /**
     * @return string[]
     */
    public function filterPaymentMethods(PaymentMethodCollection $paymentMethods, Cart $cart, SalesChannelContext $salesChannelContext): array
    {
        $handlers = [];

        $context = $this->buildAvailabilityContext($cart, $salesChannelContext);

        foreach ($paymentMethods as $paymentMethod) {
            if (!$this->isAvailable($paymentMethod, $context)) {
                $handlers[] = $paymentMethod->getHandlerIdentifier();
            }
        }

        return $handlers;
    }

    public function isPaymentMethodAvailable(PaymentMethodEntity $paymentMethod, Cart $cart, SalesChannelContext $salesChannelContext): bool
    {
        $context = $this->buildAvailabilityContext($cart, $salesChannelContext);

        return $this->isAvailable($paymentMethod, $context);
    }

    private function isAvailable(PaymentMethodEntity $paymentMethod, AvailabilityContext $context): bool
    {
        $methodData = $this->paymentMethodDataRegistry->getPaymentMethodByHandler($paymentMethod->getHandlerIdentifier());
        if ($methodData === null) {
            return true;
        }

        return $methodData->isAvailable($context);
    }

    private function buildAvailabilityContext(Cart $cart, SalesChannelContext $salesChannelContext): AvailabilityContext
    {
        $context = new AvailabilityContext();

        if (($customer = $salesChannelContext->getCustomer())
         && ($address = $customer->getActiveBillingAddress())
         && ($country = $address->getCountry())
         && ($isoCode = $country->getIso())) {
            $billingCountryCode = $isoCode;
        } else {
            $billingCountryCode = $salesChannelContext->getShippingLocation()->getCountry()->getIso();
        }

        $context->assign([
            'billingCountryCode' => $billingCountryCode,
            'currencyCode' => $salesChannelContext->getCurrency()->getIsoCode(),
            'totalAmount' => $cart->getPrice()->getTotalPrice(),
        ]);

        return $context;
    }
}
