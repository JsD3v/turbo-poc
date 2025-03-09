<?php

namespace App\Twig\Components;

use App\Entity\Message;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class MessageComponent
{
    public Message $message;
}
