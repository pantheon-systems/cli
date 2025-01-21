<?php

namespace Pantheon\Terminus\UnitTests\Helpers;

use Pantheon\Terminus\Helpers\AliasEmitters\Template;
use Pantheon\Terminus\UnitTests\TerminusTestCase;

class TemplateTest extends TerminusTestCase
{
    /**
     * processTestValues provides the expected results and inputs for testProcess
     */
    public function processTestValues()
    {
        $replacementsWithoutDbUrl = [
            '{{site_name}}' => 'MYSITE',
            '{{env_name}}' => 'MULTIDEV',
            '{{env_label}}' => 'MULTIDEV',
            '{{site_id}}' => '00000000-0000-0000-0000-000000000000',
        ];

        $replacementsWithDbUrl = $replacementsWithoutDbUrl + [
            '{{db_password}}' => 'SECRETSECRET',
            '{{db_port}}' => '10101',
        ];

        $siteYmlWithoutDbUrl = <<<EOT
'MULTIDEV':
  host: appserver.MULTIDEV.00000000-0000-0000-0000-000000000000.drush.in
  paths:
    files: files
  uri: MULTIDEV-MYSITE.pantheonsite.io
  user: MULTIDEV.00000000-0000-0000-0000-000000000000
  ssh:
    options: '-p 2222'
    tty: false
EOT;

        $siteYmlWithDbUrl = <<<EOT
'MULTIDEV':
  host: appserver.MULTIDEV.00000000-0000-0000-0000-000000000000.drush.in
  paths:
    files: files
  uri: MULTIDEV-MYSITE.pantheonsite.io
  user: MULTIDEV.00000000-0000-0000-0000-000000000000
  ssh:
    options: '-p 2222'
    tty: false
EOT;

        return [
            [
                $siteYmlWithoutDbUrl,
                'fragment.site.yml.tmpl',
                $replacementsWithoutDbUrl,
            ],

            [
                $siteYmlWithDbUrl,
                'fragment.site.yml.tmpl',
                $replacementsWithDbUrl,
            ],
        ];
    }

    /**
     * testProcess confirms that template replacements can be made correctly
     *
     * @dataProvider processTestValues
     */
    public function testProcess($expected, $filename, $replacements)
    {
        $actual = Template::process($filename, $replacements);
        $this->assertEquals(trim($expected), trim($actual));
    }
}
