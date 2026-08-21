<?php
declare(strict_types=1);

namespace Magento\Directory\Model;

class Country
{
    public function loadByCode(string $code): self
    {
        return $this;
    }

    public function getName(): ?string
    {
        return null;
    }
}
