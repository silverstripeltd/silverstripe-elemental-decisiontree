<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Tests;

use DNADesign\SilverStripeElementalDecisionTree\Model\ElementDecisionTree;
use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeStep;
use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer;
use DNADesign\SilverStripeElementalDecisionTree\Extensions\ElementDecisionTreeController;
use DNADesign\SilverStripeElementalDecisionTree\Forms\DecisionTreeStepPreview;
use SilverShop\HasOneField\HasOneButtonField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Security\Member;
use DNADesign\SilverStripeElementalDecisionTree\Forms\HasOneSelectOrCreateField;

class ElementDecisionTreeTest extends FunctionalTest
{
    protected static $fixture_file = 'ElementDecisionTreeTest.yml';

    protected static $use_draft_site = true;

    public function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(ElementDecisionTreeController::class, 'enable_requirements', false);
    }

    public function testCreateDecisionTree()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $this->assertEquals('Test Decision Tree', $tree->Title);
    }

    public function testCreateMultiStepDecisionTree()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');

        $this->assertEquals($step1->ID, $tree->FirstStep()->ID);
        $this->assertEquals($step2->ID, $step1->Answers()->first()->ResultingStep()->ID);
    }

    public function testDecisionTreeInteraction()
    {
        $page = $this->objFromFixture('DNADesign\SilverStripeElementalDecisionTree\Tests\Src\TestPage', 'page1');
        $page->publishRecursive();

        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');

        $response = $this->get($page->Link());
        $this->assertEquals(200, $response->getStatusCode());

        $response = $this->post(
            $this->getNextStepForAnswerLink($page),
            ['stepanswerid' => $answer1->ID]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Step 2', $response->getBody());
    }

    public function testAjaxInteraction()
    {
        $page = $this->objFromFixture('DNADesign\SilverStripeElementalDecisionTree\Tests\Src\TestPage', 'page1');
        $page->publishRecursive();

        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');

        $response = $this->post(
            $this->getNextStepForAnswerLink($page),
            ['stepanswerid' => $answer1->ID],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('html', $data);
        $this->assertArrayHasKey('nexturl', $data);
        $this->assertStringContainsString('Step 2', $data['html']);
    }

    public function testGetNextStepForAnswerError()
    {
        $page = $this->objFromFixture('DNADesign\SilverStripeElementalDecisionTree\Tests\Src\TestPage', 'page1');
        $page->publishRecursive();

        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');

        $response = $this->post(
            $this->getNextStepForAnswerLink($page),
            ['stepanswerid' => 999]
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetInitialPathway()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');

        $controller = $tree->getController();
        $this->assertNull($controller->getInitialPathway());

        $request = new HTTPRequest('GET', $tree->Link(), ['decisionpathway' => $answer1->ID . ',' . $step2->ID]);
        $controller->setRequest($request);

        $this->assertEquals([$answer1->ID, $step2->ID], $controller->getInitialPathway());
    }

    public function testGetIsAnswerSelected()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');

        $controller = $tree->getController();
        $this->assertFalse($controller->getIsAnswerSelected($answer1->ID));

        $request = new HTTPRequest('GET', $tree->Link(), ['decisionpathway' => (string)$answer1->ID]);
        $controller->setRequest($request);

        $this->assertTrue($controller->getIsAnswerSelected($answer1->ID));
    }

    public function testGetNextStepFromSelectedAnswer()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');

        $controller = $tree->getController();
        $this->assertNull($controller->getNextStepFromSelectedAnswer($step1->ID));

        $request = new HTTPRequest('GET', $tree->Link(), ['decisionpathway' => (string)$answer1->ID]);
        $controller->setRequest($request);

        $this->assertEquals($step2->ID, $controller->getNextStepFromSelectedAnswer($step1->ID)->ID);
    }

    public function testGetAnswerPathway()
    {
        $step3 = $this->objFromFixture(DecisionTreeStep::class, 'step3');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $answer2 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer2');

        $this->assertEquals([$answer2->ID, $answer1->ID], $step3->getAnswerPathway());
    }

    public function testGetQuestionPathway()
    {
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');
        $step3 = $this->objFromFixture(DecisionTreeStep::class, 'step3');

        $this->assertEquals([$step3->ID, $step2->ID, $step1->ID], $step3->getQuestionPathway());
    }

    public function testGetFullPathway()
    {
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');
        $step3 = $this->objFromFixture(DecisionTreeStep::class, 'step3');
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $answer2 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer2');

        $expected = [
            ['question' => $step3->ID],
            ['answer' => $answer2->ID],
            ['question' => $step2->ID],
            ['answer' => $answer1->ID],
            ['question' => $step1->ID],
        ];

        $this->assertEquals($expected, $step3->getFullPathway());
    }

    public function testGetTreeOrigin()
    {
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $step3 = $this->objFromFixture(DecisionTreeStep::class, 'step3');

        $this->assertEquals($step1->ID, $step3->getTreeOrigin()->ID);
    }

    public function testGetPositionInPathway()
    {
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');
        $step3 =
            $this->objFromFixture(DecisionTreeStep::class, 'step3');

        $this->assertEquals(1, $step1->getPositionInPathway());
        $this->assertEquals(2, $step2->getPositionInPathway());
        $this->assertEquals(3, $step3->getPositionInPathway());
    }

    public function testBelongsTo()
    {
        $step1 = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $step2 = $this->objFromFixture(DecisionTreeStep::class, 'step2');

        $this->assertTrue($step1->belongsToTree());
        $this->assertTrue($step1->belongsToElement());
        $this->assertFalse($step1->belongsToAnswer());

        $this->assertFalse($step2->belongsToElement());
        $this->assertTrue($step2->belongsToAnswer());
    }

    public function testTitleWithQuestion()
    {
        $answer1 = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $this->assertEquals('Step 1 > Answer 1', $answer1->TitleWithQuestion());
    }

    public function testOnBeforeWrite()
    {
        $result = $this->objFromFixture(DecisionTreeStep::class, 'result_no_title');
        $result->write();
        $this->assertEquals('Our recommendation', $result->Title);
    }

    public function testGetOrphans()
    {
        $orphans = DecisionTreeStep::get_orphans();
        $this->assertCount(2, $orphans);
        $this->assertContains($this->objFromFixture(DecisionTreeStep::class, 'orphan_step')->ID, $orphans->column('ID'));
    }

    public function testGetInitialSteps()
    {
        $initial = DecisionTreeStep::get_initial_steps();
        $this->assertCount(2, $initial);
        $this->assertContains($this->objFromFixture(DecisionTreeStep::class, 'step1')->ID, $initial->column('ID'));
    }

    public function testPermissions()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $answerWithResult = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $answerWithoutResult = DecisionTreeAnswer::create(['Title' => 'No result']);
        $answerWithoutResult->write();

        // Test with no permissions
        $this->logOut();
        $this->assertFalse($tree->canCreate());
        $this->assertFalse($tree->canEdit());
        $this->assertFalse($tree->canDelete());
        $this->assertFalse($answerWithResult->canDelete());

        // Test with CMS access
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $this->assertTrue($tree->canCreate());
        $this->assertTrue($tree->canEdit());
        $this->assertTrue($tree->canDelete());
        // canDelete on answer depends on ResultingStep
        $this->assertFalse($answerWithResult->canDelete());
        $this->assertTrue($answerWithoutResult->canDelete());

        // Test with ADMIN access
        $this->logInWithPermission('ADMIN');
        $this->assertTrue($tree->canCreate());
        $this->assertTrue($tree->canEdit());
        $this->assertTrue($tree->canDelete());
        $this->assertFalse($answerWithResult->canDelete());
        $this->assertTrue($answerWithoutResult->canDelete());
    }

    public function testElementDecisionTreeCMSFields()
    {
        $tree = $this->objFromFixture(ElementDecisionTree::class, 'tree1');
        $fields = $tree->getCMSFields();
        $this->assertNotNull($fields->dataFieldByName('Introduction'));
        $this->assertNotNull($fields->dataFieldByName('FirstStep'));
        $this->assertInstanceOf(HasOneButtonField::class, $fields->dataFieldByName('FirstStep'));
        $this->assertNotNull($fields->fieldByName('Root.Tree.Tree'));
        $this->assertInstanceOf(DecisionTreeStepPreview::class, $fields->fieldByName('Root.Tree.Tree'));

        $newTree = ElementDecisionTree::create();
        $newFields = $newTree->getCMSFields();
        $this->assertNotNull($newFields->fieldByName('Root.Main.info'));
        $this->assertInstanceOf(LiteralField::class, $newFields->fieldByName('Root.Main.info'));
    }

    public function testDecisionTreeStepCMSFields()
    {
        $step = $this->objFromFixture(DecisionTreeStep::class, 'step1');
        $fields = $step->getCMSFields();
        $this->assertInstanceOf(OptionsetField::class, $fields->dataFieldByName('Type'));
        $this->assertNotNull($fields->dataFieldByName('HideTitle'));
        $this->assertNotNull($fields->dataFieldByName('Answers'));
        $this->assertInstanceOf(GridField::class, $fields->dataFieldByName('Answers'));

        $result = $this->objFromFixture(DecisionTreeStep::class, 'step3');
        $resultFields = $result->getCMSFields();
        $this->assertInstanceOf(GridField::class, $resultFields->dataFieldByName('Answers'));
    }

    public function testDecisionTreeAnswerCMSFields()
    {
        $answer = $this->objFromFixture(DecisionTreeAnswer::class, 'answer1');
        $fields = $answer->getCMSFields();
        $this->assertNotNull($fields->dataFieldByName('ResultingStep'));
        $this->assertInstanceOf(HasOneButtonField::class, $fields->dataFieldByName('ResultingStep'));

        $newAnswer = DecisionTreeAnswer::create();
        $newFields = $newAnswer->getCMSFields();
        $this->assertNotNull($newFields->fieldByName('Root.Main.info'));
        $this->assertInstanceOf(LiteralField::class, $newFields->fieldByName('Root.Main.info'));
    }

    protected function getNextStepForAnswerLink(\Page $page): string
    {
        return \PageController::create($page)->Link('getNextStepForAnswer');
    }
}
