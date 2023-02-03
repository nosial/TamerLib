# TamerLib

Coming soon...

## Terminology

### Components

 - **Supervisor** - The main component of the library, this is the component that is responsible for manging
                    workers
 - **Worker** - The component that is responsible for executing tasks
 - **Task** - The component that is responsible for executing a function or closure

### Function Names
 - **do** - Execute a function in the background without blocking the current thread,
            this does not return a value. (This is a fire and forget function)
 - **doClosure** - Execute a closure in the background without blocking the current thread,
                   this does not return a value. (This is a fire and forget function) 
 - **queue** - Queues a function to be executed in the background until the next time the run function is called.
 - **queueClosure** - Queues a closure to be executed in th background until the next time the run function is called.
 - **run** - Executes all queued functions and closures in parallel and waits for the tasks to complete.


# License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
