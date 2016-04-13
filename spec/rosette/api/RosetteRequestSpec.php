<?php

namespace spec\rosette\api;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RosetteRequestSpec extends ObjectBehavior
{
    function let()
    {
        $headers = array("X-RosetteAPI-Key: user_key",
                          "Content-Type: application/json",
                          "Accept: application/json",
                          "Accept-Encoding: gzip",);
        $this->beConstructedWith('https://api.rosette.com/rest/v1', null, $headers, 'GET');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('\rosette\api\RosetteRequest');
    }

    function it_sends_a_request()
    {
        $this->makeRequest(Argument::any(), Argument::any(), Argument::any(), Argument::any())->shouldBeBool();
    }

    function it_returns_a_code()
    {
        $this->getResponseCode()->shouldBeInteger();
    }

    function it_returns_a_response()
    {
        $this->getResponse()->shouldBeArray();
    }

}
