Использование класса UserSearch

Фильтры передаются как JSON объекты. 
Каждый фильтр может содержать другие фильтры. 

Примеры фильтров:

- (ID = 1000) ИЛИ (Страна != Россия)
{
    "logical_operator": "OR",
    "conditions": [
        {
            "comparison_operator": "=",
            "param": "id",
            "value": "1"
        },
        {
            "comparison_operator": "!=",
            "param": "country",
            "value": "Russia"
        }
    ]
};
- (Страна = Россия) И (Состояние пользователя != active)
{
    "logical_operator": "AND",
    "conditions": [
        {
            "comparison_operator": "!=",
            "param": "state",
            "value": "active"
        },
        {
            "comparison_operator": "=",
            "param": "country",
            "value": "Russia"
        }
    ]
};
- ((Страна != Россия) ИЛИ (Состояние пользователя = active)) И (E-Mail = user@domain.com)
{
    "logical_operator": "AND",
    "conditions": [
        {
            "comparison_operator": "=",
            "param": "email",
            "value": "user@domain.com"
        },
        {
            "logical_operator": "OR",
            "conditions": [
                {
                    "comparison_operator": "!=",
                    "param": "country",
                    "value": "Russia"
                },
                {
                    "comparison_operator": "=",
                    "param": "state",
                    "value": "active"
                }
            ] 
        }
    ]
}

$params = $_REQUEST['filters']; // JSON объект в виде строки
$userSearch = new UserSearch($params);
$userSearch->dbconfig =  [
    'dsn' => "mysql:host=localhost;dbname=mailiq",
    'username' => 'root',
    'password' => 'root'
];
$userSearch->search();