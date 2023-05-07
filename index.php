<?php
    include 'parsedown.php';

    $QUIZ_LEN = 5;
    $QUIZ_DIRECTORY = "questions";

    $KEY_QUESTION = "question";
    $KEY_HINT = "hint";
    $KEY_EXPLANATION = "explanation";
    $KEY_SOLUTION = "solution";

    $FILENAME_QUESTION = "question.rs";
    $FILENAME_HINT = "hint.md";
    $FILENAME_EXPLANATION = "explanation.md";
    $FILENAME_SOLUTION = "solution.txt";

    $QUIZZES = [1, 2, 3, 4, 6, 8, 9];
    $ANSWERS = [
        "The program exhibits undefined behavior",
        "The program does not compile",
        "The program is guaranteed to output:",
    ];

    /// @description    Load a quiz from directory.
    /// @input          Quiz key to load. Must be a valid quiz directory.
    /// @output         Quiz array with the keys $KEY_*
    function load_quiz(int $key): array {
        global $QUIZ_DIRECTORY;
        global $KEY_QUESTION, $KEY_HINT, $KEY_EXPLANATION, $KEY_SOLUTION;
        global $FILENAME_QUESTION, $FILENAME_HINT, $FILENAME_EXPLANATION, $FILENAME_SOLUTION;

        return [
            $KEY_QUESTION => file_get_contents($QUIZ_DIRECTORY."/".$key."/".$FILENAME_QUESTION),
            $KEY_HINT => file_get_contents($QUIZ_DIRECTORY."/".$key."/".$FILENAME_HINT),
            $KEY_EXPLANATION => file_get_contents($QUIZ_DIRECTORY."/".$key."/".$FILENAME_EXPLANATION),
            $KEY_SOLUTION => file_get_contents($QUIZ_DIRECTORY."/".$key."/".$FILENAME_SOLUTION),
        ];
    }

    /// @description    Parses the current parameters and returns a random quiz key
    ///                 for which the user has not given an answer yet.
    /// @input          The parameters.
    /// @output         A quiz key, which can be resolved to a quiz using `load_quiz()`.
    /// @SAFETY         Caller must guarantee that an unsolved key exists, for example by
    ///                 asserting that sizeof($parameters) < $QUIZ_LEN!
    function new_unique_key(array $parameters): int {
        global $QUIZZES;

        while(true) {
            $key = $QUIZZES[array_rand($QUIZZES)];
            if(!isset($parameters[$key])) {
                return $key;
            }
        }
    }

    /// @description    Parses query parameters.
    /// @output         Key-value array.
    function get_parameters(): array {
        if(isset($_SERVER['QUERY_STRING'])) {
            $tmp = [];
            foreach (explode("&", urldecode($_SERVER['QUERY_STRING'])) as $parameter) {
                $inner = explode("=", $parameter);
                $tmp[$inner[0]] = $inner[1];
            }
            return $tmp;
        } else {
            return [];
        }
    }

    /// @description    Renders and returns a HTML page, showing a quiz to solve.
    /// @input          Indexed array of key-value pairs, with '=' separator.
    /// @output         HTML String.
    /// @SAFETY         Calls unsafe function `new_unique_key`
    function render_quiz(array $parameters): String {
        global $QUIZ_LEN;
        global $KEY_QUESTION, $KEY_HINT;
        global $ANSWERS;

        $quizzes_remaining = $QUIZ_LEN - sizeof($parameters);
        
        $key = new_unique_key($parameters);
        $quiz = load_quiz($key);

        $hint = $quiz[$KEY_HINT];
        $question = $quiz[$KEY_QUESTION];
        $question = htmlspecialchars($question);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta http-equiv="X-UA-Compatible" content="IE=edge">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>🦀 Crabby Cuizz</title>
                    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
                    <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
                    <script>hljs.highlightAll();</script>
                    <link rel="stylesheet" href="common.css">
                    <script defer>
                        document.addEventListener("DOMContentLoaded", () => {

                            document.getElementById("show-hint").addEventListener("click", () => {
                                document.getElementById("hint").removeAttribute('hidden');
                            });

                            document.getElementById("submit").addEventListener("click", () => {
                                const selection = document.querySelector('input[name="{$key}"]:checked');

                                if(selection == null) {
                                    return;
                                }

                                let url = new URL(window.location.href);

                                if(selection.id == "2") {
                                    url.searchParams.append("{$key}", selection.id + ":" + document.getElementById("userinput").value);
                                } else {
                                    url.searchParams.append("{$key}", selection.id);
                                }

                                window.location.href = url.href;
                            });

                        });
                    </script>
                    <!--
                        --- Copyright Notice ---
                        Quiz written by David Tolnay.
                        Found here: https://dtolnay.github.io/rust-quiz
                        And here: https://github.com/dtolnay/rust-quiz
                        Licensed under CC-BY-SA.
                        ------------------------
                    --->
                </head>
                <body>
                    <h1 style="padding-top: 4em; text-align: center;">What is the output of this Rust program?</h1>
                    <div style="width: 100%; display: inline-flex;">
                        <div style="width: 50%;">
                            <pre><code style="float: right; padding-left: 4em; padding-right: 4em;">{$question}</code></pre>
                        </div>
                        <div style="width: 50%;">
                            <div style="float: left; padding: 1em;">
                                <div>
                                    <input type="radio" name={$key} id="0">
                                    <label for="0">{$ANSWERS[0]}</label>
                                    <br>

                                    <input type="radio" name={$key} id="1">
                                    <label for="1">{$ANSWERS[1]}</label>
                                    <br>

                                    <input type="radio" name={$key} id="2">
                                    <label for="2">{$ANSWERS[2]}</label>

                                    <input type="text" id="userinput">
                                    <br>

                                    <br>
                                    <button id="submit">Submit</button>
                                    <button onClick="window.location.reload()">Skip</button>
                                    <button type="button" id="show-hint">Hint</button>
                                </div>
                                <br>
                                <div style="width: 50%;" id="hint" hidden>{$hint}</div>
                            </div>
                        </div>
                    </div>
                    <footer>
                        <div>{$quizzes_remaining} questions remaining</div>
                    </footer>
                </body>
            </html>
        HTML;
    }

    function render_results(array $parameters): String {
        global $KEY_QUESTION, $KEY_EXPLANATION, $KEY_SOLUTION;
        global $ANSWERS;
        global $QUIZ_LEN;

        $rows = <<<HTML
        <tr style="text-align: center; font-size: 1.5em;">
            <td>Question</td>
            <td>Your answer</td>
            <td>Correct answer</td>
            <td>Explanation</td>
        </tr>
        HTML;

        $correct = 0;

        foreach ($parameters as $key => $value) {
            $quiz = load_quiz($key);

            $question = $quiz[$KEY_QUESTION];
            $explanation = $quiz[$KEY_EXPLANATION];
            $solution = $quiz[$KEY_SOLUTION];

            $tmp = explode(":", $value);
            $user_answer = $ANSWERS[$tmp[0]];
            if(isset($tmp[1])) {
                $user_answer = $user_answer."  ".$tmp[1];
            }

            $tmp = explode(":", $solution);
            $correct_answer = $ANSWERS[$tmp[0]];
            if(isset($tmp[1])) {
                $correct_answer = $correct_answer."  ".$tmp[1];
            }

            if($user_answer == $correct_answer) {
                $correct += 1;
            } 

            $parsedown = new Parsedown();
            $explanation = $parsedown->text($explanation);

            $rows = $rows . <<<HTML
                <tr>
                    <td><pre><code>{$question}</code></pre></td>
                    <td>{$user_answer}</td>
                    <td>{$correct_answer}</td>
                    <td>{$explanation}</td>
                </tr>
            HTML;
        }

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta http-equiv="X-UA-Compatible" content="IE=edge">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>🦀 Crabby Cuizz</title>
                    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
                    <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
                    <script>hljs.highlightAll();</script>
                    <link rel="stylesheet" href="common.css">
                    <!--
                        --- Copyright Notice ---
                        Quiz written by David Tolnay.
                        Found here: https://dtolnay.github.io/rust-quiz
                        And here: https://github.com/dtolnay/rust-quiz
                        Licensed under CC-BY-SA.
                        ------------------------
                    --->
                </head>
                <body>
                    <div style="padding-top: 4em; text-align: center;">
                        <h1>Results are in ✨🦀</h1>
                        <h2>You got {$correct}/{$QUIZ_LEN} questions correct.</h2>
                    </div>

                    <table style="width: 80%;">
                        {$rows}
                    </table>

                    <footer>
                        <a href="/">Reset Quiz</a>
                    </footer>
                </body>
            </html>
        HTML;
    }

    /*
     *  Begin of Control Flow
     */

    $parameters = get_parameters();

    if(sizeof($parameters) < $QUIZ_LEN) {
        echo render_quiz($parameters);
    } else {
        echo render_results($parameters);
    }
?>