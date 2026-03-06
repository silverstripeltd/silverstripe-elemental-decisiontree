<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Tasks;

use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer;
use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeStep;
use DNADesign\SilverStripeElementalDecisionTree\Model\ElementDecisionTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * GenerateLargeDecisionTreeTask
 *
 * A build task to generate a large, complex decision tree for performance testing.
 *
 * This task creates:
 * - 1 ElementDecisionTree element
 * - Multiple levels of questions (configurable depth)
 * - Multiple answers per question (configurable branching)
 * - Multiple result steps
 *
 * The generated tree can be used to:
 * - Test caching performance
 * - Test query optimization
 * - Benchmark pathway calculations
 * - Test grid rendering with large datasets
 *
 * Configuration via environment variables:
 * - DECISION_TREE_DEPTH: How many levels deep (default: 5)
 * - DECISION_TREE_BRANCHES: How many answers per question (default: 4)
 * - DECISION_TREE_RESULTS: How many result steps at leaf nodes (default: 3)
 *
 * Usage:
 *   php sake dev/tasks/GenerateLargeDecisionTreeTask
 *   php sake dev/tasks/GenerateLargeDecisionTreeTask depth=6 branches=5
 *
 * @package DNADesign\SilverStripeElementalDecisionTree\Tasks
 */
class GenerateLargeDecisionTreeTask extends BuildTask
{
    /**
     * @var string
     */
    protected static string $commandName = 'GenerateLargeDecisionTreeTask';

    /**
     * @var string
     */
    protected string $title = 'Generate Large Decision Tree for Performance Testing';

    /**
     * @var string
     */
    protected static string $description = 'Creates a large decision tree with multiple levels and branches for testing performance';

    /**
     * Tree generation configuration
     */
    private int $depth = 5;          // How many levels of questions
    private int $branches = 4;       // How many answers per question
    private int $numResults = 3;     // How many unique result steps
    private array $resultSteps = [];  // Cache of created result steps
    private int $stepCount = 0;      // Total steps created
    private int $answerCount = 0;    // Total answers created

    /**
     * Execute the task
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        // Get configuration from request parameters
        $this->depth = (int) $input->getArgument('depth') ?: $this->depth;
        $this->branches = (int) $input->getArgument('branches') ?: $this->branches;
        $this->numResults = (int) $input->getArgument('results') ?: $this->numResults;

        $output->writeln("Starting Large Decision Tree Generation...");
        $output->writeln("=========================================\n");
        $output->writeln("Configuration:");
        $output->writeln("  Depth: {$this->depth} levels");
        $output->writeln("  Branches: {$this->branches} answers per question");
        $output->writeln("  Result Steps: {$this->numResults} unique results\n");

        // Create result steps first
        $this->createResultSteps($output);

        // Delete existing large tree if it exists
        $existing = ElementDecisionTree::get()->filter('Title', 'Large Performance Test Tree')->first();
        if ($existing) {
            $output->writeln("Deleting existing tree...");
            $existing->delete();
        }

        // Create the main tree element
        $tree = ElementDecisionTree::create([
            'Title' => 'Large Performance Test Tree',
            'Introduction' => '<p>This is a large decision tree generated for performance testing.</p>',
        ]);
        $tree->write();

        $output->writeln("Creating decision tree...");

        // Create the tree structure
        $firstStep = $this->createQuestionStep('Root Question', 0);
        $tree->FirstStepID = $firstStep->ID;
        $tree->write();

        // Build the tree recursively
        $this->buildTreeLevel($firstStep, 1);

        $output->writeln("\n=========================================");
        $output->writeln("✅ Decision Tree Generated Successfully!\n");
        $output->writeln("Tree Statistics:");
        $output->writeln("  Total Question Steps: {$this->stepCount}");
        $output->writeln("  Total Answers: {$this->answerCount}");
        $output->writeln("  Total Result Steps: " . count($this->resultSteps) . "");
        $output->writeln("  Total Objects: " . ($this->stepCount + $this->answerCount + count($this->resultSteps)) . "");
        $output->writeln("  Estimated Tree Depth: {$this->depth}");
        $output->writeln("  Estimated Max Breadth: {$this->branches}\n");

        $output->writeln("Performance Testing Tips:");
        $output->writeln("  1. Test pathways with: \$step->getFullPathway()");
        $output->writeln("  2. Test answers with: \$step->getAnswerPathway()");
        $output->writeln("  3. Test questions with: \$step->getQuestionPathway()");
        $output->writeln("  4. Monitor caching performance in logs");
        $output->writeln("  5. Check grid rendering performance in CMS\n");

        $output->writeln("View tree in CMS:");
        $output->writeln("  Access the page containing this element in the CMS");
        $output->writeln("  Edit the 'Large Performance Test Tree' element\n");

        return Command::SUCCESS;
    }

    /**
     * Create result/recommendation steps
     *
     * These are the leaf nodes of the tree - the final recommendations
     */
    private function createResultSteps(PolyOutput $output): void
    {
        $output->writeln("Creating result steps...");

        $results = [
            'Recommendation A: Use Solution A' => 'Based on your answers, we recommend Solution A. This is ideal for situations where X and Y are important factors.',
            'Recommendation B: Use Solution B' => 'Based on your answers, we recommend Solution B. This approach works best when Z is a priority.',
            'Recommendation C: Contact Support' => 'Based on your answers, we recommend contacting our support team for a personalized recommendation.',
        ];

        foreach ($results as $title => $content) {
            $step = DecisionTreeStep::create([
                'Title' => $title,
                'Type' => 'Result',
                'Content' => '<p>' . htmlspecialchars($content) . '</p>',
                'HideTitle' => false,
            ]);
            $step->write();
            $this->resultSteps[] = $step;
        }

        $output->writeln("  Created {$this->numResults} result steps");
    }

    /**
     * Recursively build the tree structure
     *
     * @param DecisionTreeStep $parentStep The parent question step
     * @param int $level Current depth level
     */
    private function buildTreeLevel(DecisionTreeStep $parentStep, int $level, $output): void
    {
        // Base case: if we've reached max depth, stop
        if ($level > $this->depth) {
            return;
        }

        // Create answers for this question
        for ($i = 1; $i <= $this->branches; $i++) {
            // Create an answer
            $answer = DecisionTreeAnswer::create([
                'Title' => "Option {$i} at Level {$level}",
                'QuestionID' => $parentStep->ID,
                'Sort' => $i - 1,
            ]);
            $answer->write();
            $this->answerCount++;

            // Decide if this answer should point to another question or a result
            $isLastLevel = ($level >= $this->depth);

            if ($isLastLevel) {
                // Point to a result step
                $resultStep = $this->resultSteps[($i - 1) % count($this->resultSteps)];
                $answer->ResultingStepID = $resultStep->ID;
                $answer->write();
            } else {
                // Create a new question step and link it
                $nextQuestion = $this->createQuestionStep(
                    "Level {$level} - Question {$i}",
                    $level
                );
                $answer->ResultingStepID = $nextQuestion->ID;
                $answer->write();

                // Recursively build the next level
                $this->buildTreeLevel($nextQuestion, $level + 1, $output);
            }
        }

        // Progress indicator
        if ($level <= 3) {
            $output->writeln("  Level {$level}: Created " . ($this->branches ** $level) . " question branches");
        }
    }

    /**
     * Create a question step
     *
     * @param string $title The title for the question
     * @param int $level The level in the tree (for numbering)
     * @return DecisionTreeStep
     */
    private function createQuestionStep(string $title, int $level): DecisionTreeStep
    {
        $step = DecisionTreeStep::create([
            'Title' => $title,
            'Type' => 'Question',
            'Content' => sprintf(
                '<p>This is a question at level %d. Please select one of the available options to proceed.</p>',
                $level
            ),
            'HideTitle' => false,
        ]);
        $step->write();
        $this->stepCount++;

        return $step;
    }

    /**
     * Get summary of the task
     *
     * @return string
     */
    public static function getSummary(): string
    {
        return 'Generates a large decision tree for performance testing the module';
    }

}

