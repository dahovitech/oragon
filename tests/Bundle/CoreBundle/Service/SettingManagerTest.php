<?php

namespace App\Tests\Bundle\CoreBundle\Service;

use App\Bundle\CoreBundle\Service\SettingManager;
use App\Bundle\CoreBundle\Entity\Setting;
use App\Bundle\CoreBundle\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SettingManagerTest extends TestCase
{
    private SettingManager $settingManager;
    private EntityManagerInterface|MockObject $entityManager;
    private SettingRepository|MockObject $settingRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->settingRepository = $this->createMock(SettingRepository::class);
        $this->settingManager = new SettingManager($this->entityManager, $this->settingRepository);
    }

    public function testGetExistingSetting(): void
    {
        $setting = new Setting();
        $setting->setSettingKey('test_key');
        $setting->setSettingValue('test_value');
        $setting->setType('string');

        $this->settingRepository
            ->expects($this->once())
            ->method('getAsArray')
            ->willReturn(['test_key' => 'test_value']);

        $result = $this->settingManager->get('test_key');
        
        $this->assertEquals('test_value', $result);
    }

    public function testGetNonExistentSetting(): void
    {
        $this->settingRepository
            ->expects($this->once())
            ->method('getAsArray')
            ->willReturn([]);

        $result = $this->settingManager->get('non_existent', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }

    public function testSetNewSetting(): void
    {
        $this->settingRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with('new_key')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Setting::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $setting = $this->settingManager->set('new_key', 'new_value', 'string', 'test');
        
        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertEquals('new_key', $setting->getSettingKey());
    }

    public function testHasExistingSetting(): void
    {
        $setting = new Setting();
        $setting->setSettingKey('existing_key');

        $this->settingRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with('existing_key')
            ->willReturn($setting);

        $result = $this->settingManager->has('existing_key');
        
        $this->assertTrue($result);
    }

    public function testHasNonExistentSetting(): void
    {
        $this->settingRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with('non_existent')
            ->willReturn(null);

        $result = $this->settingManager->has('non_existent');
        
        $this->assertFalse($result);
    }

    public function testValidateValidEmail(): void
    {
        $errors = $this->settingManager->validateValue('email', 'test@example.com');
        $this->assertEmpty($errors);
    }

    public function testValidateInvalidEmail(): void
    {
        $errors = $this->settingManager->validateValue('email', 'invalid-email');
        $this->assertContains('Adresse email invalide', $errors);
    }

    public function testValidateValidJson(): void
    {
        $errors = $this->settingManager->validateValue('json', '{"key": "value"}');
        $this->assertEmpty($errors);
    }

    public function testValidateInvalidJson(): void
    {
        $errors = $this->settingManager->validateValue('json', '{"invalid": json}');
        $this->assertContains('JSON invalide', $errors);
    }
}