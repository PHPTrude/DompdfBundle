<?php

declare(strict_types=1);

/*
 * (c) Christian Gripp <mail@core23.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Core23\DompdfBundle\Wrapper;

use Core23\DompdfBundle\DompdfEvents;
use Core23\DompdfBundle\Event\OutputEvent;
use Core23\DompdfBundle\Event\StreamEvent;
use Core23\DompdfBundle\Exception\PdfException;
use Core23\DompdfBundle\Factory\DompdfFactoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DompdfWrapper implements DompdfWrapperInterface
{
    /**
     * @var DompdfFactoryInterface
     */
    private $dompdfFactory;

    /**
     * @var EventDispatcherInterface|null
     */
    private $eventDispatcher;

    public function __construct(DompdfFactoryInterface $dompdfFactory, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->dompdfFactory   = $dompdfFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function streamHtml(string $html, string $filename, array $options = []): void
    {
        $pdf = $this->dompdfFactory->create($options);
        $pdf->loadHtml($html);
        $pdf->render();

        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $event = new StreamEvent($pdf, $filename, $html);
            $this->eventDispatcher->dispatch($event, DompdfEvents::STREAM);
        }

        $pdf->stream($filename, $options);
    }

    public function getStreamResponse(string $html, string $filename, array $options = []): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function () use ($html, $filename, $options): void {
            $this->streamHtml($html, $filename, $options);
        });

        return $response;
    }

    public function getPdf(string $html, array $options = []): string
    {
        $pdf = $this->dompdfFactory->create($options);
        $pdf->loadHtml($html);
        $pdf->render();

        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $event = new OutputEvent($pdf, $html);
            $this->eventDispatcher->dispatch($event, DompdfEvents::OUTPUT);
        }

        $out = $pdf->output();

        if (null === $out) {
            throw new PdfException('Error creating PDF document');
        }

        return $out;
    }
}
