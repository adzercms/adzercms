#!/usr/bin/env python3
import json
from importlib.metadata import version
from pathlib import Path

import pymorphy3

TOKENS = [
    'А', 'Ё', 'Я', 'Q', 'я', 'и', 'в',
    '0', '123', '٣٤', '１２', '1e5', '1E10', '٣e٥', 'nan', 'NaN', 'inf', 'Infinity', 'infinityx', '1' * 4301, '²', 'Ⅻ', '½',
    'XIV', 'mmxxiv', 'mix', 'İV', 'ıv', 'IIII',
    'pdf', 'Ａｂｃ', 'abc123', 'straße', 'ª', 'İstanbul', 'ǅemal',
    'ΣΑΣ', 'λόγος', 'abcабв', '2ой', '10кг', 'х5',
    'псевдокошка', 'антивирусник', 'суперкот', 'мегасупердом', 'недокот', 'гиперпупер',
    'байткод', 'кракозябра', 'зюкошка',
    'бутявкать', 'бутявка', 'покрасивее', 'наикрутейший', 'глокая', 'куздра', 'бокр', 'бокренок',
    'ыыы', 'ъъъъ', 'щщ', 'жжжжжжжж', 'кот', 'котёнок',
    'елка', 'еж', 'все', 'берет', 'ее', 'Ёлка', 'ЕЛКА', 'Москва', 'МОСКВА', 'ООО',
    'электрификация', 'достопримечательность', 'человеконенавистничество',
]
LETTERS = 'абвгдежзийклмнопрстуфхцчшщэюя'
PER_LETTER = 5
STRIDE = 997


def sample(morph):
    tokens = []
    for letter in LETTERS:
        keys = zip(range(STRIDE * PER_LETTER), morph.dictionary.words.iterkeys(letter))
        for word in [key for position, key in keys if position % STRIDE == 0 and key.isalnum()]:
            tokens += [word, word.replace('ё', 'е'), word.upper(), 'псевдо' + word, 'зю' + word, 'бутяв' + word[-4:]]
    return tokens


def main():
    morph = pymorphy3.MorphAnalyzer()
    parses = {}
    for token in TOKENS + sample(morph):
        parses.setdefault(token, [[str(p.tag), p.normal_form, p.score] for p in morph.parse(token)])
    versions = {name: version(name) for name in ('pymorphy3', 'pymorphy3-dicts-ru', 'DAWG2-Python')}
    lines = [json.dumps(token, ensure_ascii=False) + ': ' + json.dumps(value, ensure_ascii=False) for token, value in parses.items()]
    text = '{\n"versions": ' + json.dumps(versions) + ',\n"parses": {\n' + ',\n'.join(lines) + '\n}\n}\n'
    Path(__file__).with_name('reference.json').write_text(text, encoding='utf-8')


main()
