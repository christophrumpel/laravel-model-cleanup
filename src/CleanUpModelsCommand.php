<?php

namespace Spatie\DatabaseCleanup;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\ParserFactory;
use ClassPreloader\Parser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class CleanUpModelsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'clean:models';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up models.';

    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
    }

    public function handle()
    {
        $this->comment('Cleaning models...');

        $models = $this->getModelsThatShouldBeCleanedUp();

        $this->cleanUp($models);

        $this->comment('All done!');
    }

    protected function getModelsThatShouldBeCleanedUp() : Collection
    {
        $directories = config('laravel-database-cleanup.directories');

        $modelsFromDirectories = $this->getAllModelsOfEachDirectory($directories);

        $cleanableModels = $modelsFromDirectories
            ->merge(collect(config('laravel-database-cleanup.models')))
            ->flatten()
            ->filter(function ($modelClass) {

                return in_array(GetsCleanedUp::class, class_implements($modelClass));
            });

        return $cleanableModels;
    }

    protected function cleanUp(Collection $models)
    {
        $models->each(function (string $class) {

            $query = $class::cleanUp($class::query());

            $numberOfDeletedRecords = $query->delete();

            $this->info("Deleted {$numberOfDeletedRecords} record(s) from {$class}).");

        });
    }

    protected function getAllModelsOfEachDirectory(array $directories) : Collection
    {
        return collect($directories)->map(function ($directory) {

            return $this->getClassNames($directory)->all();

        });
    }

    protected function getClassNames(string $directory) : Collection
    {
        return collect($this->filesystem->files($directory))->map(function ($path) {

            return $this->getFullyQualifiedClassNameFromFile($path);
        });
    }

    protected function getFullyQualifiedClassNameFromFile(string $path) : string
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $traverser = new NodeTraverser();

        $traverser->addVisitor(new NameResolver());

        $code = file_get_contents($path);

        $statements = $parser->parse($code);

        $statements = $traverser->traverse($statements);

        return collect($statements[0]->stmts)
            ->filter(function ($statement) {
                return $statement instanceof Class_;
            })
            ->map(function (Class_ $statement) {
                return $statement->namespacedName->toString();
            })
            ->first();
    }
}
