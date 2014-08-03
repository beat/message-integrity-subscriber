<?php

namespace GuzzleHttp\Subscriber\MessageIntegrity;

use GuzzleHttp\Stream\MetadataStreamInterface;
use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;

/**
 * Stream decorator that validates a rolling hash of the entity body as it is
 * read.
 *
 * @todo Allow the file pointer to skip around and read bytes randomly
 */
class ReadIntegrityStream implements StreamInterface
{
    //BB use StreamDecoratorTrait;
	/** @var StreamInterface Decorated stream */
	private $stream;

	/*BB
	 * @param StreamInterface $stream Stream to decorate
	 *
	public function __construct(StreamInterface $stream)
	{
		$this->stream = $stream;
	}
	*/

	public function __toString()
	{
		try {
			$this->seek(0);
			return $this->getContents();
		} catch (\Exception $e) {
			// Really, PHP? https://bugs.php.net/bug.php?id=53648
			trigger_error('StreamDecorator::__toString exception: '
				. (string) $e, E_USER_ERROR);
			return '';
		}
	}

	public function getContents($maxLength = -1)
	{
		return \GuzzleHttp\Stream\copy_to_string($this, $maxLength);
	}

	/**
	 * Allow decorators to implement custom methods
	 *
	 * @param string $method Missing method name
	 * @param array  $args   Method arguments
	 *
	 * @return mixed
	 */
	public function __call($method, array $args)
	{
		$result = call_user_func_array(array($this->stream, $method), $args);

		// Always return the wrapped object if the result is a return $this
		return $result === $this->stream ? $this : $result;
	}

	public function close()
	{
		return $this->stream->close();
	}

	public function getMetadata($key = null)
	{
		return $this->stream instanceof MetadataStreamInterface
			? $this->stream->getMetadata($key)
			: null;
	}

	public function detach()
	{
		$this->stream->detach();

		return $this;
	}

	public function getSize()
	{
		return $this->stream->getSize();
	}

	public function eof()
	{
		return $this->stream->eof();
	}

	public function tell()
	{
		return $this->stream->tell();
	}

	public function isReadable()
	{
		return $this->stream->isReadable();
	}

	public function isWritable()
	{
		return $this->stream->isWritable();
	}

	public function isSeekable()
	{
		return $this->stream->isSeekable();
	}

	public function seek($offset, $whence = SEEK_SET)
	{
		return $this->stream->seek($offset, $whence);
	}

	/*BB
	public function read($length)
	{
		return $this->stream->read($length);
	}
	*/

	public function write($string)
	{
		return $this->stream->write($string);
	}
    //BB end of StreamDecoratorTrait;

    /** @var HashInterface */
    private $hash;

    /** @var callable|null */
    private $validationCallback;

    /** @var int Last position that the hash was updated at */
    private $lastHashPos = 0;

    /** @var bool */
    private $expected;

    /**
     * @param StreamInterface $stream   Stream that is validated
     * @param HashInterface   $hash     Hash used to calculate the rolling hash
     * @param string          $expected The expected hash result.
     * @param callable        $onFail   Optional function to invoke when there
     *     is a mismatch between the calculated hash and the expected hash.
     *     The callback is called with the resulting hash and the expected hash.
     *     This callback can be used to throw specific exceptions.
     */
    public function __construct(
        StreamInterface $stream,
        HashInterface $hash,
        $expected,
        callable $onFail = null
    ) {
        $this->stream = $stream;
        $this->hash = $hash;
        $this->validationCallback = $onFail;
        $this->expected = $expected;
    }

    public function read($length)
    {
        $data = $this->stream->read($length);
        // Only update the hash if this data has not already been read
        if ($this->tell() >= $this->lastHashPos) {
            $this->hash->update($data);
            $this->lastHashPos += $length;
            if ($this->eof()) {
                $result = $this->hash->complete();
                if ($this->expected !== $result) {
                    $this->mismatch($result);
                }
            }
        }
    }

    private function mismatch($result)
    {
        if ($this->validationCallback) {
            call_user_func(
                $this->validationCallback,
                $result,
                $this->expected
            );
        }

        throw new \UnexpectedValueException(
            sprintf('Message integrity check failure. Expected %s '
                . 'but got %s', $this->expected, $result)
        );
    }
}
