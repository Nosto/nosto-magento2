<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Console\Command;

use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Util\Customer as CustomerUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NostoGenerateCustomerReferenceCommand extends Command
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * NostoGenerateCustomerReferenceCommand constructor.
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setName('nosto:generate:customer-reference')
            ->setDescription('Generate automatically customer_reference for all customer missing it');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $customerCollection = $this->collectionFactory->create()
                ->addAttributeToSelect([
                    'entity_id',
                    NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME])
                ->addAttributeToFilter(
                    NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                    ["null" => true]
                )
                ->load();

            $customers = $customerCollection->getItems();
            /** @var CustomerInterface $customer */
            foreach ($customers as $customer) {
                /**
                 * Argument is of type \Magento\Framework\DataObject
                 * but CustomerInterface|\Magento\Customer\Model\Backend\Customer\Interceptor is expected
                 */
                /** @phan-suppress-next-line PhanTypeMismatchArgumentSuperType */
                $customerUtil = new CustomerUtil();
                $customerReference = $customerUtil->generateCustomerReference($customer);
                /** @noinspection PhpUndefinedMethodInspection */
                $customer->setData(
                    NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                    $customerReference
                );
                /** @noinspection PhpUndefinedMethodInspection */
                $customer->save();
            }
            $io->success('Operation finished with success');
            return 0;
        } catch (Exception $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
