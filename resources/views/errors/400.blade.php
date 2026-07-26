@include('errors.layout', [
    'code' => '400',
    'title' => 'Bad request',
    'message' => 'The request could not be understood. Check the URL or form and try again.',
])
