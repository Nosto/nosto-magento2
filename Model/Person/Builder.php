<?php
/**
 * Created by PhpStorm.
 * User: hannupolonen
 * Date: 19/03/18
 * Time: 16:41
 */

namespace Nosto\Tagging\Model\Person;


use Nosto\Object\ModelFilter;
use Nosto\Object\Order\Buyer;
use Nosto\Tagging\Model\Email\Repository as NostoEmailRepository;
use Nosto\Types\PersonInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;


abstract class Builder
{
    /**
     * @var NostoEmailRepository
     */
    private $emailRepository;
    /**
     * @var EventManager
     */
    private $eventManager;

    public function __construct(
        NostoEmailRepository $emailRepository,
        EventManager $eventManager
    )
    {
        $this->emailRepository = $emailRepository;
        $this->eventManager = $eventManager;
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phone
     * @param string $postCode
     * @param string $country
     *
     * @return PersonInterface
     */
    public function build(
        $firstName,
        $lastName,
        $email,
        $phone,
        $postCode,
        $country
    ) {
        $modelFilter = new ModelFilter();
        $this->eventManager->dispatch('nosto_person_load_before', ['modelFilter' => $modelFilter]);
        if (!$modelFilter->isValid()) {
            return null;
        }
        $person = $this->buildObject($firstName, $lastName, $email, $phone, $postCode, $country);
        $person->setMarketingPermission(
            $this->emailRepository->isOptedIn($person->getEmail())
        );
        $this->eventManager->dispatch('nosto_person_load_after', [
            'modelFilter' => $modelFilter,
            'person' => $person
        ]);

        return $person;
    }

    /**
     * @param $firstName
     * @param $lastName
     * @param $email
     * @param $phone
     * @param $postCode
     * @param $country
     * @return PersonInterface|Buyer
     */
    abstract public function buildObject(
        $firstName,
        $lastName,
        $email,
        $phone,
        $postCode,
        $country
    );
}